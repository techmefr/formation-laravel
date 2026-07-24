<?php

namespace Database\Seeders;

use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Seances\Services\InscriptionService;
use Functional\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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
                        $seance = Seance::factory()
                            ->recycle($placeCoaches)
                            ->create([
                                'name' => $type,
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

        $this->seedWaitlistShowcase($collaborators);
    }

    /**
     * @param  Collection<int, User>  $collaborators
     */
    private function seedWaitlistShowcase(Collection $collaborators): void
    {
        $demo = $collaborators->firstWhere('email', 'collab@example.com');

        if ($demo === null) {
            return;
        }

        $others = $collaborators->where('email', '!=', 'collab@example.com')->take(4)->values();
        $chosen = $others->push($demo);

        $showcase = Seance::whereNull('cancelled_at')
            ->whereHas('place', fn ($place) => $place->where('type', 'external')->orWhere('id', $demo->agency_id))
            ->orderBy('started_at')
            ->get()
            ->unique(fn (Seance $seance) => $seance->started_at->toIso8601String())
            ->take(3);

        foreach ($showcase as $seance) {
            $seance->update(['max_participants' => 3]);

            foreach ($chosen as $collaborator) {
                $overlapIds = $collaborator->seances()
                    ->where('started_at', '<', $seance->ended_at)
                    ->where('ended_at', '>', $seance->started_at)
                    ->pluck('seances.id');

                $collaborator->seances()->detach($overlapIds);
            }

            $seance->participants()->detach();

            foreach ($chosen->values() as $index => $collaborator) {
                $seance->participants()->attach($collaborator->id, [
                    'status' => $index < 3 ? 'registered' : 'waitlist',
                    'position' => $index + 1,
                ]);
            }
        }
    }
}
