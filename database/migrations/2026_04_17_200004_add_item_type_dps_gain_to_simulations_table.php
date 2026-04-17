<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulations', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('character_id')->constrained()->nullOnDelete();
            $table->string('type')->default('standalone')->after('item_id');
            $table->float('dps_gain')->nullable()->after('results');
        });
    }

    public function down(): void
    {
        Schema::table('simulations', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn(['item_id', 'type', 'dps_gain']);
        });
    }
};
