<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::table('accounting_entries',fn(Blueprint $t)=>$t->unsignedBigInteger('legacy_payment_id')->nullable()->unique());
 // Keep the source records as an audit archive; new balances use entries only.
 foreach(DB::table('accounting_payments')->orderBy('id')->cursor() as $p){$e=DB::table('accounting_entries')->find($p->entry_id);DB::table('accounting_entries')->insert(['member_id'=>$e->member_id,'directory_id'=>$e->directory_id,'catalog_id'=>$e->catalog_id,'person'=>$e->person,'type'=>$e->type==='receivable'?'receipt':'payment','currency'=>$e->currency,'amount'=>$p->amount,'date'=>$p->date,'note'=>'Eski işlem #'.$e->id.' ödeme kaydından taşındı. '.($p->note??''),'legacy_payment_id'=>$p->id,'deleted_at'=>$e->deleted_at,'created_at'=>$p->created_at,'updated_at'=>now()]);}
 // An excess receipt was previously represented as debt. It is the same credit,
 // not a second receipt; change its classification rather than duplicating it.
 DB::table('accounting_entries')->where('received_advance','>',0)->update(['type'=>'receipt','received_advance'=>0]);
 Schema::create('accounting_reminders',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained('enerjisa_users');$t->foreignId('directory_id')->constrained('accounting_directory');$t->timestampTz('next_send_at');$t->timestampTz('last_sent_at')->nullable();$t->string('status')->default('active');$t->text('last_error')->nullable();$t->timestamps();$t->unique(['member_id','directory_id']);});
 }
 public function down(): void {throw new RuntimeException('Independent receipt migration cannot be reversed without a reviewed data restore.');}
};
