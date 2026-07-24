<?php

namespace Database\Factories;

use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * @var class-string<Seance>
     */
    protected $model = Seance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'name' => fake()->randomElement(['Yoga', 'CrossFit', 'Pilates', 'Renforcement', 'Cardio']),
            'coach_id' => User::factory(),
            'place_id' => Place::factory(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(40, 120).' minutes'),
            'max_participants' => fake()->numberBetween(5, 30),
            'recurrence' => 'none',
            'recurrence_until' => null,
        ];
    }
}
