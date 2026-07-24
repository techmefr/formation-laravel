<?php

namespace App\Services;

use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InscriptionService
{
    public function register(Seance $seance, User $user): string
    {
        if ($seance->participants()->whereKey($user->id)->exists()) {
            return 'already';
        }

        if ($this->hasTimeConflict($seance, (int) $user->id)) {
            return 'conflict';
        }

        $status = $seance->isFull() ? 'waitlist' : 'registered';
        $position = $seance->participants()->count() + 1;

        $seance->participants()->attach($user->id, [
            'status' => $status,
            'position' => $position,
        ]);

        return $status;
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
        $candidates = $seance->participants()
            ->wherePivot('status', 'waitlist')
            ->orderByPivot('position')
            ->get();

        foreach ($candidates as $candidate) {
            if (! $this->hasTimeConflict($seance, (int) $candidate->getKey())) {
                $seance->participants()->updateExistingPivot($candidate->getKey(), ['status' => 'registered']);

                return;
            }
        }
    }

    private function hasTimeConflict(Seance $seance, int $userId): bool
    {
        if ($seance->ended_at === null) {
            return false;
        }

        $registeredSeanceIds = DB::table('seance_user')
            ->where('user_id', $userId)
            ->where('status', 'registered')
            ->pluck('seance_id');

        return Seance::query()
            ->whereIn('id', $registeredSeanceIds)
            ->whereNull('cancelled_at')
            ->where('started_at', '<', $seance->ended_at)
            ->where('ended_at', '>', $seance->started_at)
            ->exists();
    }
}
