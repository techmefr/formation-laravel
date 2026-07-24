<?php

namespace Functional\Seances\Rest\Actions;

use Functional\Seances\Models\Seance;
use Functional\Seances\Services\InscriptionService;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action as RestAction;
use Lomkit\Rest\Http\Requests\RestRequest;

class UnregisterAction extends RestAction
{
    public function __construct(private InscriptionService $inscriptions) {}

    /**
     * Désinscription de l'utilisateur authentifié (self-service, même
     * périmètre que RegisterAction : aucune policy, chacun gère sa propre inscription).
     *
     * @param  Collection<int, Seance>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        /** @var User $user */
        $user = auth()->user();

        foreach ($models as $seance) {
            $this->inscriptions->unregister($seance, $user);
        }
    }

    public function fields(RestRequest $request): array
    {
        return [];
    }
}
