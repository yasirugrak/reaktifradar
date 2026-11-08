<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::create('accounting_receipts',function(Blueprint $t){$t->id();$t->foreignId('member_id')->constrained('enerjisa_users');$t->foreignId('directory_id')->constrained('accounting_directory');$t->uuid('token')->unique();$t->bigInteger('amount');$t->string('currency',8);$t->date('date');$t->json('allocation');$t->timestamps();});}
 public function down(): void {Schema::dropIfExists('accounting_receipts');}
};
