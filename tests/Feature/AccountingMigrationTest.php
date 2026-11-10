<?php
namespace Tests\Feature;
use App\Accounting\Ledger;
use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccountingMigrationTest extends TestCase {
 use RefreshDatabase;
 public function test_old_allocations_and_excess_keep_the_same_balance(): void {
 Schema::drop('accounting_reminders');Schema::table('accounting_entries',fn(Blueprint $t)=>$t->dropColumn('legacy_payment_id'));
 $account=Account::create(['name'=>'Test']);$member=Member::create(['account_id'=>$account->id,'name'=>'Test','email'=>'migration@example.test','password'=>'Password12345']);
 $contact=DB::table('accounting_directory')->insertGetId(['member_id'=>$member->id,'kind'=>'person','name'=>'Ada','normalized_name'=>'ada']);
 $base=['member_id'=>$member->id,'directory_id'=>$contact,'person'=>'Ada','currency'=>'TRY','date'=>'2026-11-01','created_at'=>now(),'updated_at'=>now()];
 $sale=DB::table('accounting_entries')->insertGetId($base+['type'=>'receivable','amount'=>1000000]);
 DB::table('accounting_payments')->insert(['entry_id'=>$sale,'amount'=>1000000,'date'=>'2026-11-02','token'=>(string)Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
 DB::table('accounting_entries')->insert($base+['type'=>'debt','amount'=>200000,'received_advance'=>200000]);
 $migration=require database_path('migrations/2026_11_10_000001_independent_receipts.php');$migration->up();
 $this->assertSame(-200000,Ledger::balances($member->id,$contact)['TRY']);$this->assertDatabaseCount('accounting_payments',1);$this->assertSame(2,DB::table('accounting_entries')->where('type','receipt')->count());
 }
}
