<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enerjisa_notification_deliveries', fn (Blueprint $table) => $table->text('email_data')->nullable());
    }

    public function down(): void
    {
        Schema::table('enerjisa_notification_deliveries', fn (Blueprint $table) => $table->dropColumn('email_data'));
    }
};
