<?php

namespace Database\Factories;

use App\Domain\Character\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'class' => fake()->randomElement(['warrior', 'mage', 'rogue', 'paladin', 'druid']),
            'race' => fake()->randomElement(['human', 'dwarf', 'nightelf', 'orc', 'troll']),
            'spec' => fake()->randomElement(['arms', 'fury', 'protection']),
            'level' => 80,
            'stats' => null,
            'gear' => null,
            'simc_profile' => 'warrior="TestChar"'."\nlevel=80\nrace=dwarf\nspec=arms",
        ];
    }
}
