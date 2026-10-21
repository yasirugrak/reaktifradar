<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_scans', function (Blueprint $table) {
            $table->string('provider', 20)->default('google');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_scans', fn (Blueprint $table) => $table->dropColumn('provider'));
    }
};
