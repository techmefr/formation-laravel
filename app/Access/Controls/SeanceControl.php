<?php

namespace App\Access\Controls;

use App\Access\Perimeters\GlobalPerimeter;
use App\Access\Perimeters\OwnPerimeter;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class SeanceControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model = Seance::class;

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            // Admin/manager : accès à toutes les séances (mêmes conditions que
            // OwnPerimeter, sauf que should() n'est pas restreint à leurs propres séances).
            GlobalPerimeter::new()
                ->allowed(function (User $user, string $method) {
                    if (! $user->hasRole(['admin', 'manager'])) {
                        return false;
                    }

                    return $this->allowedForMethod($user, $method);
                })
                ->should(fn (User $user, Model $model) => true)
                ->query(fn (Builder $query, User $user) => $query),

            // Coach/collaborateur : viewAny/view ouverts à tous (comme l'ancienne
            // SeancePolicy), create/update/delete uniquement sur ses propres séances.
            OwnPerimeter::new()
                ->allowed(fn (User $user, string $method) => $this->allowedForMethod($user, $method))
                ->should(fn (User $user, Seance $model) => $model->coach_id === $user->id)
                ->query(fn (Builder $query, User $user) => $query->where('coach_id', $user->id)),
        ];
    }

    private function allowedForMethod(User $user, string $method): bool
    {
        if (in_array($method, ['viewAny', 'view'], true)) {
            return true;
        }

        return $user->can("{$method} seances");
    }
}
