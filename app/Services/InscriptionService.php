<?php

namespace App\Services;

use App\Models\Seance;
use App\Models\User;

class InscriptionService
{
    public function register(Seance $seance, User $user): void
    {
        if ($seance->participants()->whereKey($user->id)->exists()) {
            return;
        }

        $status = $seance->isFull() ? 'waitlist' : 'registered';
        $position = $seance->participants()->count() + 1;

        $seance->participants()->attach($user->id, [
            'status' => $status,
            'position' => $position,
        ]);
    }

    public function unregister(Seance $seance, User $user): void
    {
        $wasRegistered = $seance->participants()
            ->whereKey($user->id)
            ->wherePivot('status', 'registered')
            ->exists();

        $seance->participants()->detach($user->id);

        if ($wasRegistered) {
            $this->promoteFirstWaitlisted($seance);
        }
    }

    private function promoteFirstWaitlisted(Seance $seance): void
    {
        /** @var User|null $next */
        $next = $seance->participants()
            ->wherePivot('status', 'waitlist')
            ->orderByPivot('position')
            ->first();

        if ($next !== null) {
            $seance->participants()->updateExistingPivot($next->id, ['status' => 'registered']);
        }
    }
}
