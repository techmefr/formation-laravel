<?php

namespace App\Policies;

use App\Access\Controls\SeanceControl;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Policies\ControlledPolicy;

class SeancePolicy extends ControlledPolicy
{
    /**
     * @var class-string<SeanceControl>
     */
    protected string $control = SeanceControl::class;

    /**
     * Ouvert à tout utilisateur connecté, contrairement à viewAny/create/update/delete :
     * pas de notion de "ses propres séances" pour consulter le détail d'une séance.
     */
    public function view(Model $user, Model $model): bool
    {
        return true;
    }

    public function cancel(User $user, Seance $seance): bool
    {
        return $user->can('cancel seances') && $this->ownsOrManages($user, $seance);
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
