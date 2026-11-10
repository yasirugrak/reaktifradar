<?php
namespace Tests\Feature;
use App\Accounting\Ledger;
use App\Accounting\ReminderSender;
use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;
class IndependentLedgerTest extends TestCase {
 use RefreshDatabase;
 private function setupAccount(): array {
 Http::fake(['*'=>Http::response('',503)]);$this->travelTo(now()->setDate(2026,11,10)->setTime(10,0));
 $account=Account::create(['name'=>'Test']);$member=Member::create(['account_id'=>$account->id,'name'=>'Test','email'=>'ledger@example.test','password'=>'Password12345']);$this->actingAs($member,'enerjisa');
 $id=DB::table('accounting_directory')->insertGetId(['member_id'=>$member->id,'kind'=>'person','name'=>'Ada','normalized_name'=>'ada','created_at'=>now(),'updated_at'=>now()]);return [$member,$id];
 }
 private function entry(int $contact,string $type,string $amount): int {$this->post('/muhasebe/entries',['token'=>(string)Str::uuid(),'directory_id'=>$contact,'type'=>$type,'amount'=>$amount,'currency'=>'TRY','date'=>'2026-11-10'])->assertSessionHasNoErrors();return DB::table('accounting_entries')->max('id');}
 public function test_independent_receipts_balance_and_ui(): void {
 [$member,$id]=$this->setupAccount();$sale=$this->entry($id,'receivable','100');$receipt=$this->entry($id,'receipt','40');
 $this->assertSame(600000,Ledger::balances($member->id,$id)['TRY']);
 $this->get('/muhasebe?contact_id='.$id)->assertOk()->assertSee('Tahsilat')->assertSee('100,00')->assertSee('40,00')->assertSee('60,00')->assertDontSee('Tahsilat ekle')->assertDontSee('Dağılımı önizle');
 $this->get('/muhasebe')->assertOk()->assertSee('60,00');
 $this->post('/muhasebe/entries/'.$sale.'/payments',['amount'=>'10'])->assertNotFound();$this->post('/muhasebe/tahsilat')->assertNotFound();
 $this->postJson('/muhasebe/entries/'.$sale.'/amount',['amount'=>'30'])->assertOk();$this->assertSame(-100000,Ledger::balances($member->id,$id)['TRY']);
 $this->delete('/muhasebe/entries/'.$sale);$this->assertSame(-400000,Ledger::balances($member->id,$id)['TRY']);$this->assertDatabaseHas('accounting_entries',['id'=>$receipt,'deleted_at'=>null]);
 $this->post('/muhasebe/entries/'.$sale.'/restore');$this->assertSame(-100000,Ledger::balances($member->id,$id)['TRY']);
 }
 public function test_reminders_repeat_weekly_and_stop_when_closed(): void {
 [$member,$id]=$this->setupAccount();$this->entry($id,'receivable','100');$this->entry($id,'receipt','40');
 $this->post('/muhasebe/reminders/'.$id,['date'=>'2026-11-10'])->assertSessionHasNoErrors();
 $sent=[];Mail::shouldReceive('raw')->twice()->andReturnUsing(function($body,$callback)use(&$sent,$member){$sent[]=$body;$message=new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);$callback($message);$this->assertSame($member->email,$message->getSymfonyMessage()->getTo()[0]->getAddress());});
 $sender=new ReminderSender;$this->assertSame(1,$sender->run());$this->assertStringContainsString('60,00 TRY',$sent[0]);$this->assertSame(0,$sender->run());
 $this->travel(6)->days();$this->assertSame(0,$sender->run());$this->travel(1)->days();$this->assertSame(1,$sender->run());
 $this->entry($id,'receipt','60');$this->travel(7)->days();$this->assertSame(0,$sender->run());$this->assertDatabaseHas('accounting_reminders',['directory_id'=>$id,'status'=>'closed']);
 }
 public function test_failed_mail_is_visible_and_not_retried_automatically(): void {
 [$member,$id]=$this->setupAccount();$this->entry($id,'receivable','10');$this->post('/muhasebe/reminders/'.$id,['date'=>'2026-11-10']);
 Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
 $sender=new ReminderSender;$this->assertSame(0,$sender->run());$this->assertDatabaseHas('accounting_reminders',['directory_id'=>$id,'status'=>'failed']);$this->assertSame(0,$sender->run());
 $this->get('/muhasebe?contact_id='.$id)->assertOk()->assertSee('Gönderim hatası');
 }
 public function test_cancelled_reminder_does_not_send_and_currencies_do_not_cancel_each_other(): void {
 [$member,$id]=$this->setupAccount();$this->entry($id,'receivable','10');
 $this->post('/muhasebe/entries',['token'=>(string)Str::uuid(),'directory_id'=>$id,'type'=>'receipt','amount'=>'10','currency'=>'USD','date'=>'2026-11-10'])->assertSessionHasNoErrors();
 $this->assertSame(['TRY'=>100000,'USD'=>-100000],Ledger::balances($member->id,$id)->sortKeys()->all());
 $this->post('/muhasebe/reminders/'.$id,['date'=>'2026-11-10']);$this->delete('/muhasebe/reminders/'.$id)->assertSessionHasNoErrors();Mail::shouldReceive('raw')->never();$this->assertSame(0,(new ReminderSender)->run());
 }
 public function test_reminder_ownership(): void {[$member,$id]=$this->setupAccount();$other=Member::create(['account_id'=>$member->account_id,'name'=>'Other','email'=>'other-ledger@example.test','password'=>'Password12345']);$this->actingAs($other,'enerjisa')->post('/muhasebe/reminders/'.$id,['date'=>'2026-11-10'])->assertNotFound();}
}
