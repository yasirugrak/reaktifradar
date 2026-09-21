<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enerjisa_notification_rules', function (Blueprint $table) {
            $table->string('telegram_link_hash', 64)->nullable()->unique();
            $table->timestampTz('telegram_link_expires_at')->nullable();
            $table->timestampTz('telegram_connected_at')->nullable();
        });
        // Old chat IDs belonged to customer-owned bots, not the central bot.
        DB::table('enerjisa_notification_rules')->update(['telegram_enabled' => false, 'chat_id' => null]);
    }

    public function down(): void
    {
        Schema::table('enerjisa_notification_rules', fn (Blueprint $table) => $table->dropColumn(['telegram_link_hash', 'telegram_link_expires_at', 'telegram_connected_at']));
    }
};
