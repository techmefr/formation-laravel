<?php

namespace App\Rest\Actions;

use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\Action as RestAction;
use Lomkit\Rest\Http\Requests\RestRequest;

class RemoveParticipantAction extends RestAction
{
    public function __construct(private InscriptionService $inscriptions) {}

    /**
     * Désinscrit un autre utilisateur, même périmètre que AddParticipantAction :
     * gardé par la policy `manageParticipants`.
     *
     * @param  Collection<int, Seance>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $participant = User::findOrFail($fields['user_id']);

        foreach ($models as $seance) {
            Gate::authorize('manageParticipants', $seance);

            $this->inscriptions->unregister($seance, $participant);
        }
    }

    public function fields(RestRequest $request): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }
}
