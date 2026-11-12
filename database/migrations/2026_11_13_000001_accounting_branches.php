<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::create('accounting_branches',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained('enerjisa_users')->restrictOnDelete();$t->string('name',100);$t->boolean('is_main')->default(false);$t->timestamps();$t->unique(['member_id','name']);});
  foreach(['directory','catalog','entries','quotes','profiles','reminders','receipts'] as $name) Schema::table('accounting_'.$name,fn(Blueprint $t)=>$t->foreignId('branch_id')->nullable()->constrained('accounting_branches')->restrictOnDelete());
  foreach(DB::table('enerjisa_users')->pluck('id') as $member){$branch=DB::table('accounting_branches')->insertGetId(['member_id'=>$member,'name'=>'Ana Şube','is_main'=>true,'created_at'=>now(),'updated_at'=>now()]);foreach(['directory','catalog','entries','quotes','profiles','reminders','receipts'] as $name)DB::table('accounting_'.$name)->where('member_id',$member)->update(['branch_id'=>$branch]);}
  foreach(['directory','catalog'] as $name)Schema::table('accounting_'.$name,function(Blueprint $t)use($name){$t->dropUnique(['member_id','kind','normalized_name']);$t->unique(['branch_id','kind','normalized_name'],'accounting_'.$name.'_branch_name_unique');});
  Schema::table('accounting_profiles',function(Blueprint $t){$t->dropUnique(['member_id']);$t->unique('branch_id');$t->json('bank_accounts')->nullable();});
  foreach(['directory','quotes','reminders','receipts','payments'] as $name)Schema::table('accounting_'.$name,fn(Blueprint $t)=>$t->softDeletes());
  foreach(['directory','entries','quotes','reminders','receipts','payments'] as $name)Schema::table('accounting_'.$name,fn(Blueprint $t)=>$t->uuid('deletion_batch')->nullable()->index());
  Schema::table('accounting_directory',fn(Blueprint $t)=>$t->timestamp('redlined_at')->nullable());
  Schema::table('accounting_quotes',fn(Blueprint $t)=>$t->foreignId('directory_id')->nullable()->constrained('accounting_directory')->restrictOnDelete());
  foreach(DB::table('accounting_quotes')->get() as $quote){$snapshot=json_decode($quote->recipient??'{}',true);$matches=DB::table('accounting_directory')->where('member_id',$quote->member_id)->where('branch_id',$quote->branch_id)->where('name',$snapshot['name']??$quote->person)->get()->filter(function($contact)use($snapshot){foreach(['email','phone','address','tax_number','tax_office'] as $field)if(array_key_exists($field,$snapshot)&&($contact->$field??'')!==($snapshot[$field]??''))return false;return true;});if($matches->count()===1)DB::table('accounting_quotes')->where('id',$quote->id)->update(['directory_id'=>$matches->first()->id]);}
 }
 public function down(): void {throw new RuntimeException('Şube ve silinen kayıtları korumak için bu geçiş geri alınamaz.');}
};
