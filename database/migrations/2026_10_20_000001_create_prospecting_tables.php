<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_scans', function (Blueprint $table) {
            $table->id();
            $table->string('region');
            $table->string('osb')->nullable();
            $table->string('sector')->nullable();
            $table->unsignedInteger('limit')->default(20);
            $table->string('status')->default('queued');
            $table->json('candidates')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();
        });
        Schema::create('prospect_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_scan_id')->constrained()->cascadeOnDelete();
            $table->string('company');
            $table->string('email');
            $table->text('source_url');
            $table->string('status')->default('review');
            $table->timestamps();
            $table->unique(['prospect_scan_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_contacts');
        Schema::dropIfExists('prospect_scans');
    }
};
