<?php

namespace App\Domain\ItemDatabase\Models;

use Illuminate\Database\Eloquent\Model;

class LootSource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'loot_table',
    ];

    protected $casts = [
        'loot_table' => 'array',
    ];
}
