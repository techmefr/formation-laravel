<?php

namespace App\Rest\Actions;

use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Action as RestAction;
use Lomkit\Rest\Http\Requests\RestRequest;

class AddParticipantAction extends RestAction
{
    public function __construct(private InscriptionService $inscriptions) {}

    /**
     * Inscrit un autre utilisateur (staff qui gère les participants),
     * gardé par la policy `manageParticipants` — pas du self-service.
     *
     * @param  Collection<int, Seance>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $participant = User::findOrFail($fields['user_id']);

        foreach ($models as $seance) {
            Gate::authorize('manageParticipants', $seance);

            $result = $this->inscriptions->register($seance, $participant);

            if (in_array($result, ['conflict', 'already'], true)) {
                throw ValidationException::withMessages([
                    'user_id' => match ($result) {
                        'conflict' => 'Ce participant a déjà une séance sur ce créneau horaire.',
                        default => 'Ce participant est déjà inscrit à cette séance.',
                    },
                ]);
            }
        }
    }

    public function fields(RestRequest $request): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }
}
