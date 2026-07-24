<?php

namespace Database\Factories;

use Functional\Places\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * @var class-string<Place>
     */
    protected $model = Place::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => faker()->city(),
            'description' => faker()->streetAddress(),
            'type' => 'agency',
            'code' => fake()->unique()->regexify('[A-Z0-9]{4}'),
        ];
    }
}
