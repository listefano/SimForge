<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_loot_source', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loot_source_id')->constrained()->cascadeOnDelete();
            $table->primary(['item_id', 'loot_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_loot_source');
    }
};
