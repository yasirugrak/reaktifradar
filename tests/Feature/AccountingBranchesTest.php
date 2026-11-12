<?php
namespace Tests\Feature;
use App\Accounting\{Ledger,ReminderSender};
use App\Enerjisa\Models\{Account,Member};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail};
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountingBranchesTest extends TestCase {
 use RefreshDatabase;
 private function member(): Member {
  Http::fake(['*'=>Http::response('',503)]);
  $account=Account::create(['name'=>'Branches']);$member=Member::create(['account_id'=>$account->id,'name'=>'Oktay','email'=>'branches@example.test','password'=>'Password12345']);$this->actingAs($member,'enerjisa');return $member;
 }
 private function contact(): int {$this->postJson('/muhasebe/directory',['name'=>'Ada','kind'=>'person'])->assertCreated();return DB::table('accounting_directory')->max('id');}
 private function entry(int $id): int {$this->post('/muhasebe/entries',['directory_id'=>$id,'type'=>'receivable','amount'=>'150','currency'=>'TRY','date'=>today()->toDateString(),'token'=>(string)Str::uuid()])->assertSessionHasNoErrors();return DB::table('accounting_entries')->max('id');}
 public function test_branches_are_empty_and_scope_all_resources_and_mutations(): void {
  $member=$this->member();$id=$this->contact();$entry=$this->entry($id);$main=DB::table('accounting_branches')->value('id');
  $this->post('/muhasebe/profil',['name'=>'Ana firma'])->assertSessionHasNoErrors();
  $this->postJson('/muhasebe/urunler',['name'=>'Hizmet','kind'=>'service','price'=>'10','currency'=>'TRY'])->assertCreated();$product=DB::table('accounting_catalog')->value('id');
  $this->post('/muhasebe/quotes',['recipient_id'=>$id,'currency'=>'TRY','items'=>[['catalog_id'=>$product,'quantity'=>1,'price'=>'10']]])->assertSessionHasNoErrors();$quote=DB::table('accounting_quotes')->value('id');
  $this->post('/muhasebe/subeler',['name'=>'İkinci Şube'])->assertRedirect('/muhasebe');
  $this->get('/muhasebe')->assertOk()->assertDontSee('Ada')->assertDontSee('150,00');
  $this->getJson('/muhasebe/directory')->assertExactJson([]);$this->getJson('/muhasebe/urunler')->assertExactJson([]);
  $this->get('/muhasebe/profil')->assertDontSee('Ana firma');$this->get('/muhasebe/teklifler')->assertDontSee('Ada');
  foreach(['/muhasebe?contact_id='.$id,'/muhasebe/rehber/'.$id.'/duzenle','/muhasebe/urunler/'.$product.'/data','/muhasebe/teklifler/'.$quote] as $url)$this->get($url)->assertNotFound();
  $this->postJson('/muhasebe/entries/'.$entry.'/amount',['amount'=>'1'])->assertNotFound();$this->delete('/muhasebe/entries/'.$entry)->assertNotFound();$this->post('/muhasebe/entries/'.$entry.'/restore')->assertNotFound();
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'delete'])->assertNotFound();$this->post('/muhasebe/reminders/'.$id,['date'=>today()->toDateString()])->assertNotFound();
  $new=$this->contact();$this->assertNotSame($new,$id);$this->post('/muhasebe/profil',['name'=>'Yeni firma'])->assertSessionHasNoErrors();
  $this->post('/muhasebe/subeler/sec',['branch_id'=>$main])->assertRedirect();$this->get('/muhasebe')->assertSee('150,00');$this->get('/muhasebe/profil')->assertSee('Ana firma')->assertDontSee('Yeni firma');
  $other=Member::create(['account_id'=>$member->account_id,'name'=>'Other','email'=>'other-branch@example.test','password'=>'Password12345']);$this->actingAs($other,'enerjisa')->post('/muhasebe/subeler/sec',['branch_id'=>$main])->assertNotFound();
 }
 public function test_soft_delete_cascades_and_restore_keeps_previously_deleted_entries_deleted(): void {
  $member=$this->member();$id=$this->contact();$entry=$this->entry($id);$old=$this->entry($id);$this->delete('/muhasebe/entries/'.$old);
  $branch=DB::table('accounting_branches')->value('id');
  $quote=DB::table('accounting_quotes')->insertGetId(['member_id'=>$member->id,'branch_id'=>$branch,'directory_id'=>$id,'person'=>'Ada','currency'=>'TRY','items'=>'[]','total'=>100]);
  $this->post('/muhasebe/reminders/'.$id,['date'=>today()->toDateString()])->assertSessionHasNoErrors();
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'redline'])->assertRedirect('/muhasebe/rehber?category=redlined');
  $this->get('/muhasebe')->assertDontSee('Ada');$this->get('/muhasebe/rehber?category=redlined')->assertSee('Ada')->assertSee('150,00');$this->assertSame(1500000,Ledger::balances($member->id,$id)['TRY']);
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'delete'])->assertRedirect('/muhasebe/rehber?category=deleted');
  foreach(['accounting_directory'=>$id,'accounting_entries'=>$entry,'accounting_quotes'=>$quote] as $table=>$row)$this->assertNotNull(DB::table($table)->where('id',$row)->value('deleted_at'));
  $this->assertTrue(Ledger::balances($member->id,$id)->isEmpty());$this->get('/muhasebe/rehber?category=deleted')->assertSee('Ada')->assertSee('Geri al');$this->get('/muhasebe?contact_id='.$id)->assertNotFound();
  $this->post('/muhasebe/entries/'.$entry.'/restore')->assertNotFound();$this->get('/muhasebe/teklifler/'.$quote)->assertNotFound();
  Mail::shouldReceive('raw')->never();$this->assertSame(0,(new ReminderSender)->run());
  $this->postJson('/muhasebe/directory',['name'=>'Ada','kind'=>'person'])->assertUnprocessable();
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'restore'])->assertRedirect();
  $this->assertDatabaseHas('accounting_entries',['id'=>$entry,'deleted_at'=>null]);$this->assertNotNull(DB::table('accounting_entries')->where('id',$old)->value('deleted_at'));
  $this->assertDatabaseHas('accounting_quotes',['id'=>$quote,'deleted_at'=>null]);$this->assertSame(1500000,Ledger::balances($member->id,$id)['TRY']);
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'unmark']);$this->get('/muhasebe')->assertSee('Ada');
 }
 public function test_redlined_contacts_are_excluded_from_general_totals_until_unmarked(): void {
  $this->member();$id=$this->contact();$this->entry($id);
  $this->get('/muhasebe')->assertViewHas('generalBalances',fn($balances)=>$balances['TRY']['receivable']===1500000);
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'redline'])->assertRedirect();
  foreach(['/muhasebe','/muhasebe/rehber?category=redlined','/muhasebe/rehber?q=Ada'] as $url)$this->get($url)->assertOk()->assertViewHas('generalBalances',fn($balances)=>$balances->isEmpty());
  $this->getJson('/muhasebe/rehber?q=Ada')->assertOk()->assertJsonPath('total',0)->assertDontSee('150,00');
  $this->get('/muhasebe?contact_id='.$id)->assertOk()->assertSee('150,00');
  $this->post('/muhasebe/rehber/'.$id.'/durum',['action'=>'unmark'])->assertRedirect();
  $this->get('/muhasebe')->assertViewHas('generalBalances',fn($balances)=>$balances['TRY']['receivable']===1500000);
 }
 public function test_bank_accounts_are_saved_and_printed_in_contact_statement(): void {
  $this->member();$id=$this->contact();
  $banks=[['bank_name'=>'Test Bankası','holder'=>'Örnek Firma','iban'=>'TR000000000000000000000001','currency'=>'TRY'],['bank_name'=>'Euro Bankası','holder'=>'Örnek Firma','iban'=>'DE00000000000000000000','currency'=>'EUR']];
  $this->post('/muhasebe/profil',['name'=>'Örnek Firma','bank_accounts'=>$banks])->assertSessionHasNoErrors();
  $this->get('/muhasebe?contact_id='.$id)->assertOk()->assertSee('statement-banks')->assertSee($banks[0]['iban'])->assertSee($banks[1]['iban']);
  $this->post('/muhasebe/profil',['name'=>'Örnek Firma'])->assertSessionHasNoErrors();$this->get('/muhasebe?contact_id='.$id)->assertDontSee($banks[0]['iban']);
 }
}
