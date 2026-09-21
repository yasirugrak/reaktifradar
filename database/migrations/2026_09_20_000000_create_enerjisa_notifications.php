<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enerjisa_accounts', function (Blueprint $table) {
            $table->text('notification_settings')->nullable();
        });
        Schema::create('enerjisa_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('enerjisa_accounts')->cascadeOnDelete();
            $table->string('installation', 64);
            $table->boolean('enabled')->default(false);
            $table->string('frequency')->default('daily');
            $table->string('send_time', 5)->default('09:00');
            $table->unsignedSmallInteger('weekday')->default(1);
            $table->boolean('send_healthy')->default(false);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('telegram_enabled')->default(false);
            $table->string('email')->nullable();
            $table->string('chat_id')->nullable();
            $table->timestampsTz();
            $table->unique(['account_id', 'installation']);
        });
        Schema::create('enerjisa_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('enerjisa_notification_rules')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('body')->nullable();
            $table->string('error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['rule_id', 'scheduled_date', 'channel'], 'enerjisa_delivery_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enerjisa_notification_deliveries');
        Schema::dropIfExists('enerjisa_notification_rules');
        Schema::table('enerjisa_accounts', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
    }
};
