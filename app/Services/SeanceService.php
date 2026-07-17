<?php

namespace App\Services;

use App\Models\Seance;

class SeanceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Seance
    {
        return Seance::create($data);
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
    }

    public function delete(Seance $seance): void
    {
        $seance->delete();
    }
}
