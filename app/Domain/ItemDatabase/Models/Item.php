<?php

namespace App\Domain\ItemDatabase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function lootSources(): BelongsToMany
    {
        return $this->belongsToMany(LootSource::class);
    }
}
