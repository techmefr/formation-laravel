<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Database\Seeder;

class SeanceSeeder extends Seeder
{
    public function run(InscriptionService $inscriptions): void
    {
        $coaches = User::role('coach')->get();
        $collaborators = User::role('collaborator')->get();
        $places = Place::all();
        $types = ['Yoga', 'CrossFit', 'Pilates', 'Renforcement', 'Cardio'];

        foreach ($places as $place) {
            foreach ($types as $type) {
                $base = now()->addDay()->setTime(8, 0);

                for ($i = 0; $i < 3; $i++) {
                    $startedAt = (clone $base)->addHours($i * 3);
                    $duration = fake()->numberBetween(40, 120);

                    $seance = Seance::factory()->create([
                        'name' => $type,
                        'coach_id' => $coaches->random()->id,
                        'place_id' => $place->id,
                        'started_at' => $startedAt,
                        'ended_at' => (clone $startedAt)->addMinutes($duration),
                    ]);

                    $capacity = $seance->max_participants ?? 10;
                    $count = min(fake()->numberBetween(0, $capacity + 3), $collaborators->count());

                    if ($count > 0) {
                        foreach ($collaborators->random($count) as $collaborator) {
                            $inscriptions->register($seance, $collaborator);
                        }
                    }
                }
            }
        }
    }
}
