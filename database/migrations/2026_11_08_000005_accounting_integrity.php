<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('accounting_entries',function(Blueprint $t){$t->softDeletes();$t->uuid('token')->nullable()->unique();});}
 public function down(): void {Schema::table('accounting_entries',function(Blueprint $t){$t->dropSoftDeletes();$t->dropColumn('token');});}
};
