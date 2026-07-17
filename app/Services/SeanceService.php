<?php

namespace App\Services;

use App\Events\SeanceCancelled;
use App\Events\SeanceCreated;
use App\Models\Seance;

class SeanceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Seance
    {
        $seance = Seance::create($data);

        SeanceCreated::dispatch($seance);

        return $seance;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Seance $seance, array $data): void
    {
        $seance->update($data);
    }

    public function cancel(Seance $seance): void
    {
        $seance->cancelled_at = now();
        $seance->save();

        SeanceCancelled::dispatch($seance);
    }

    public function delete(Seance $seance): void
    {
        $seance->delete();
    }
}
