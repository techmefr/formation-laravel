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
        $slots = [
            [7, 30, 8, 15],
            [12, 15, 13, 0],
            [18, 15, 19, 0],
        ];

        $start = now()->startOfWeek();

        foreach (range(0, 13) as $offset) {
            $day = $start->copy()->addDays($offset);

            foreach ($places as $place) {
                $placeCoaches = $coaches->where('agency_id', $place->id)->values();

                if ($placeCoaches->isEmpty()) {
                    $placeCoaches = $coaches;
                }

                foreach ($slots as [$startHour, $startMinute, $endHour, $endMinute]) {
                    $howMany = fake()->numberBetween(0, 3);

                    foreach (collect($types)->shuffle()->take($howMany) as $type) {
                        $seance = Seance::factory()->create([
                            'name' => $type,
                            'coach_id' => $placeCoaches->random()->id,
                            'place_id' => $place->id,
                            'started_at' => $day->copy()->setTime($startHour, $startMinute),
                            'ended_at' => $day->copy()->setTime($endHour, $endMinute),
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
}
