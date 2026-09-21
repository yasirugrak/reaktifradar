<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enerjisa_accounts', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::table('enerjisa_users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
        });
        Schema::create('enerjisa_system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enerjisa_system_settings');
        Schema::table('enerjisa_accounts', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('enerjisa_users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'last_login_at']));
    }
};
