<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulation_batches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('character_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('loot_source_id')->nullable()->after('character_id')->constrained()->nullOnDelete();
            $table->string('type')->default('standalone')->after('loot_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('simulation_batches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['character_id']);
            $table->dropForeign(['loot_source_id']);
            $table->dropColumn(['user_id', 'character_id', 'loot_source_id', 'type']);
        });
    }
};
