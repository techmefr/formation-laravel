<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Seeder;

class SeanceSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = User::role('coach')->get();
        $places = Place::all();
        $types = ['Yoga', 'CrossFit', 'Pilates', 'Renforcement', 'Cardio'];

        foreach ($places as $place) {
            foreach ($types as $type) {
                $base = now()->addDay()->setTime(8, 0);

                for ($i = 0; $i < 3; $i++) {
                    $startedAt = (clone $base)->addHours($i * 3);
                    $duration = fake()->numberBetween(40, 120);

                    Seance::factory()->create([
                        'name' => $type,
                        'coach_id' => $coaches->random()->id,
                        'place_id' => $place->id,
                        'started_at' => $startedAt,
                        'ended_at' => (clone $startedAt)->addMinutes($duration),
                    ]);
                }
            }
        }
    }
}
