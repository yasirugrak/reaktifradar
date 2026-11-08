<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {foreach(['accounting_directory','accounting_entries'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->json('tags')->nullable());}
 public function down(): void {foreach(['accounting_directory','accounting_entries'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->dropColumn('tags'));}
};
