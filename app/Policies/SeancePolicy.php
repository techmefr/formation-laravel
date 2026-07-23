<?php

namespace App\Policies;

use App\Models\Seance;
use App\Models\User;

class SeancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Seance $seance): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create seances');
    }

    public function update(User $user, Seance $seance): bool
    {
        return $user->can('update seances') && $this->ownsOrManages($user, $seance);
    }

    public function cancel(User $user, Seance $seance): bool
    {
        return $user->can('cancel seances') && $this->ownsOrManages($user, $seance);
    }

    public function delete(User $user, Seance $seance): bool
    {
        return $user->can('delete seances') && $this->ownsOrManages($user, $seance);
    }

    public function manageParticipants(User $user, Seance $seance): bool
    {
        return $user->can('manage participants') && $this->ownsOrManages($user, $seance);
    }

    private function ownsOrManages(User $user, Seance $seance): bool
    {
        return $user->hasRole(['admin', 'manager']) || $seance->coach_id === $user->id;
    }
}
