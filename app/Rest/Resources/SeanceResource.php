<?php

namespace App\Rest\Resources;

use App\Events\SeanceCreated;
use App\Events\SeanceDeleted;
use App\Models\Seance;
use App\Rest\Actions\AddParticipantAction;
use App\Rest\Actions\CancelSeanceAction;
use App\Rest\Actions\RegisterAction;
use App\Rest\Actions\RemoveParticipantAction;
use App\Rest\Actions\UnregisterAction;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;

class SeanceResource extends Resource
{
    /**
     * @var class-string<Model>
     */
    public static $model = Seance::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'coach_id',
            'place_id',
            'started_at',
            'ended_at',
            'max_participants',
            'cancelled_at',
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('coach', UserResource::class),
            BelongsTo::make('place', PlaceResource::class),
            BelongsToMany::make('participants', UserResource::class),
        ];
    }

    public function scopes(RestRequest $request): array
    {
        return [];
    }

    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    public function rules(RestRequest $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'place_id' => ['required', 'exists:places,id'],
            'coach_id' => ['required', 'exists:users,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function actions(RestRequest $request): array
    {
        return [
            app(CancelSeanceAction::class),
            app(RegisterAction::class),
            app(UnregisterAction::class),
            app(AddParticipantAction::class),
            app(RemoveParticipantAction::class),
        ];
    }

    public function instructions(RestRequest $request): array
    {
        return [];
    }

    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        assert($model instanceof Seance);

        if ($requestBody['operation'] === 'create') {
            SeanceCreated::dispatch($model);
        }
    }

    public function destroyed(DestroyRequest $request, Model $model): void
    {
        assert($model instanceof Seance);

        SeanceDeleted::dispatch($model);
    }
}
