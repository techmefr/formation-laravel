<?php

namespace App\Rest\Actions;

use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Action as RestAction;
use Lomkit\Rest\Http\Requests\RestRequest;

class RegisterAction extends RestAction
{
    public function __construct(private InscriptionService $inscriptions) {}

    /**
     * Inscription de l'utilisateur authentifié (self-service, pas de policy :
     * n'importe quel utilisateur connecté peut s'inscrire à une séance).
     *
     * @param  Collection<int, Seance>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        /** @var User $user */
        $user = auth()->user();

        foreach ($models as $seance) {
            $result = $this->inscriptions->register($seance, $user);

            if (in_array($result, ['conflict', 'already'], true)) {
                throw ValidationException::withMessages([
                    'seance' => match ($result) {
                        'conflict' => 'Vous avez déjà une séance sur ce créneau horaire.',
                        default => 'Vous êtes déjà inscrit à cette séance.',
                    },
                ]);
            }
        }
    }

    public function fields(RestRequest $request): array
    {
        return [];
    }
}
