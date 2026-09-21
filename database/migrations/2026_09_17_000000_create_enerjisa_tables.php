<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enerjisa_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('enerjisa_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('enerjisa_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestampsTz();
        });
        Schema::create('enerjisa_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('enerjisa_accounts')->cascadeOnDelete();
            $table->string('kind');
            $table->json('parameters');
            $table->text('payload')->nullable();
            $table->string('error')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enerjisa_queries');
        Schema::dropIfExists('enerjisa_users');
        Schema::dropIfExists('enerjisa_accounts');
    }
};
