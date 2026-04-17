<?php

namespace App\Domain\ItemDatabase\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'rarity',
        'item_level',
        'stats',
        'effects',
    ];

    protected $casts = [
        'stats' => 'array',
        'effects' => 'array',
    ];
}
