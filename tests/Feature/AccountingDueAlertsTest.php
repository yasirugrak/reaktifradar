<?php
namespace Tests\Feature;
use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountingDueAlertsTest extends TestCase {
 use RefreshDatabase;
 public function test_only_outstanding_owned_entries_raise_due_alerts(): void {
 $this->travelTo(now()->setDate(2026,11,9)->startOfDay());
 $account=Account::create(['name'=>'Test']);$member=Member::create(['account_id'=>$account->id,'name'=>'Test','email'=>'due@example.test','password'=>'Password12345']);$this->actingAs($member,'enerjisa');
 $id=DB::table('accounting_directory')->insertGetId(['member_id'=>$member->id,'kind'=>'person','name'=>'Ada','normalized_name'=>'ada','created_at'=>now(),'updated_at'=>now()]);
 $base=['member_id'=>$member->id,'directory_id'=>$id,'person'=>'Ada','currency'=>'TRY','amount'=>100000,'date'=>'2026-11-01','created_at'=>now(),'updated_at'=>now()];
 DB::table('accounting_entries')->insert($base+['type'=>'receivable','due'=>'2026-11-08']);
 DB::table('accounting_entries')->insert($base+['type'=>'debt','due'=>'2026-11-09']);
 $paid=DB::table('accounting_entries')->insertGetId($base+['type'=>'receivable','due'=>'2026-11-07']);
 DB::table('accounting_payments')->insert(['entry_id'=>$paid,'amount'=>100000,'date'=>'2026-11-07','token'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
 DB::table('accounting_entries')->insert($base+['type'=>'debt','due'=>'2026-11-06','deleted_at'=>now()]);
 DB::table('accounting_entries')->insert($base+['type'=>'debt','due'=>'2026-11-17']);
 $this->get('/muhasebe')->assertOk()->assertSee('1 geciken alacak')->assertSee('1 yaklaşan ödeme')->assertDontSee('2 geciken alacak')->assertDontSee('geciken ödeme');
 $this->getJson('/muhasebe/rehber?q=Ada')->assertOk()->assertJsonPath('total',1);
 }
}
