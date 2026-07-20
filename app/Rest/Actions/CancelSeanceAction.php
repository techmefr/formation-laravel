<?php

namespace App\Rest\Actions;

use App\Models\Seance;
use App\Services\SeanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\Action as RestAction;
use Lomkit\Rest\Http\Requests\RestRequest;

class CancelSeanceAction extends RestAction
{
    public function __construct(private SeanceService $seances) {}

    /**
     * @param  Collection<int, Seance>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $seance) {
            Gate::authorize('cancel', $seance);

            $this->seances->cancel($seance);
        }
    }

    public function fields(RestRequest $request): array
    {
        return [];
    }
}
