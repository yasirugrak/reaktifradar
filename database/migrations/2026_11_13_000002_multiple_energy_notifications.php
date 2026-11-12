<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enerjisa_notification_rules', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'installation']);
            $table->index(['account_id', 'installation']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Birden fazla bildirimi korumak için bu geçiş geri alınamaz.');
    }
};
