<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
 Schema::create('accounting_entries', function(Blueprint $t) {
 $t->id(); $t->foreignId('member_id')->constrained('enerjisa_users')->restrictOnDelete();
 $t->string('person',160); $t->string('type',16); $t->string('currency',8); $t->bigInteger('amount'); $t->date('date'); $t->date('due')->nullable(); $t->text('note')->nullable(); $t->timestamps(); $t->index(['member_id','date']);
 });
 Schema::create('accounting_payments', function(Blueprint $t) { $t->id(); $t->foreignId('entry_id')->constrained('accounting_entries')->restrictOnDelete(); $t->bigInteger('amount'); $t->date('date'); $t->text('note')->nullable(); $t->uuid('token')->unique(); $t->timestamps(); });
 Schema::create('accounting_quotes', function(Blueprint $t) { $t->id(); $t->foreignId('member_id')->constrained('enerjisa_users')->restrictOnDelete(); $t->string('person',160); $t->string('currency',8); $t->json('items'); $t->bigInteger('total'); $t->date('valid_until'); $t->string('status')->default('Taslak'); $t->text('note')->nullable(); $t->timestamps(); });
 }
 public function down(): void { Schema::dropIfExists('accounting_quotes'); Schema::dropIfExists('accounting_payments'); Schema::dropIfExists('accounting_entries'); }
};
