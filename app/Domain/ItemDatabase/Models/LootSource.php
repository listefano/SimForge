<?php

namespace App\Domain\ItemDatabase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }
}
