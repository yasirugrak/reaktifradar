<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::create('accounting_profiles',function(Blueprint $t){$t->id();$t->foreignId('member_id')->unique()->constrained('enerjisa_users');$t->string('name',160);$t->string('email')->nullable();$t->string('phone',40)->nullable();$t->string('tax_number',40)->nullable();$t->string('tax_office',160)->nullable();$t->text('address')->nullable();$t->timestamps();});
 Schema::table('accounting_directory',function(Blueprint $t){$t->string('email')->nullable();$t->string('phone',40)->nullable();$t->string('tax_number',40)->nullable();$t->string('tax_office',160)->nullable();$t->text('address')->nullable();});
 Schema::table('accounting_quotes',function(Blueprint $t){$t->json('sender')->nullable();$t->json('recipient')->nullable();});
 }
 public function down(): void {Schema::table('accounting_quotes',fn(Blueprint $t)=>$t->dropColumn(['sender','recipient']));Schema::table('accounting_directory',fn(Blueprint $t)=>$t->dropColumn(['email','phone','tax_number','tax_office','address']));Schema::dropIfExists('accounting_profiles');}
};
