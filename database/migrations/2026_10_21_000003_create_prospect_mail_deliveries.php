<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('prospect_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_mail_deliveries');
    }
};
