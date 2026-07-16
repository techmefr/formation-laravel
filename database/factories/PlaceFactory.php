<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('fr_FR')->city(),
            'description' => fake('fr_FR')->streetAddress(),
            'type' => 'agency',
            'code' => fake()->unique()->regexify('[A-Z0-9]{4}'),
        ];
    }
}
