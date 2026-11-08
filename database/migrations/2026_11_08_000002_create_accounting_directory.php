<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::create('accounting_directory',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained('enerjisa_users')->restrictOnDelete();$t->string('kind',16);$t->string('name',160);$t->string('normalized_name',160);$t->timestamps();$t->unique(['member_id','kind','normalized_name']);});
 Schema::table('accounting_entries',function(Blueprint $t){$t->foreignId('directory_id')->nullable()->constrained('accounting_directory')->restrictOnDelete();});
 }
 public function down(): void {Schema::table('accounting_entries',fn(Blueprint $t)=>$t->dropConstrainedForeignId('directory_id'));Schema::dropIfExists('accounting_directory');}
};
