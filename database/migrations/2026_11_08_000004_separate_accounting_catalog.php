<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
 Schema::create('accounting_catalog',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained('enerjisa_users');$t->string('kind',16);$t->string('name',160);$t->string('normalized_name',160);$t->string('unit',40)->default('adet');$t->bigInteger('price')->default(0);$t->string('currency',8)->default('TRY');$t->text('description')->nullable();$t->timestamps();$t->unique(['member_id','kind','normalized_name']);});
 Schema::table('accounting_entries',fn(Blueprint $t)=>$t->foreignId('catalog_id')->nullable()->constrained('accounting_catalog')->restrictOnDelete());
 DB::transaction(function(){foreach(DB::table('accounting_directory')->whereIn('kind',['product','service'])->get() as $row){$id=DB::table('accounting_catalog')->insertGetId(['member_id'=>$row->member_id,'kind'=>$row->kind,'name'=>$row->name,'normalized_name'=>$row->normalized_name,'created_at'=>$row->created_at,'updated_at'=>$row->updated_at]);DB::table('accounting_entries')->where('directory_id',$row->id)->update(['catalog_id'=>$id,'directory_id'=>null]);DB::table('accounting_directory')->where('id',$row->id)->delete();}});
 }
 public function down(): void {
 DB::transaction(function(){foreach(DB::table('accounting_catalog')->get() as $row){$id=DB::table('accounting_directory')->insertGetId(['member_id'=>$row->member_id,'kind'=>$row->kind,'name'=>$row->name,'normalized_name'=>$row->normalized_name,'created_at'=>$row->created_at,'updated_at'=>$row->updated_at]);DB::table('accounting_entries')->where('catalog_id',$row->id)->update(['directory_id'=>$id]);}});
 Schema::table('accounting_entries',fn(Blueprint $t)=>$t->dropConstrainedForeignId('catalog_id'));Schema::dropIfExists('accounting_catalog');
 }
};
