<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('accounting_quotes',fn(Blueprint $t)=>$t->date('valid_until')->nullable()->change());Schema::table('accounting_profiles',fn(Blueprint $t)=>$t->string('logo_path')->nullable());}
 public function down(): void {Schema::table('accounting_profiles',fn(Blueprint $t)=>$t->dropColumn('logo_path')); /* Nullable dates retained to preserve records without expiry. */}
};
