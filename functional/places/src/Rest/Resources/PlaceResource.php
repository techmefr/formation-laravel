<?php

namespace Functional\Places\Rest\Resources;

use Functional\Places\Models\Place;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\RestApi\Http\Resources\Resource;

class PlaceResource extends Resource
{
    /**
     * @var class-string<Model>
     */
    public static $model = Place::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'type',
            'code',
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [];
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

    public function actions(RestRequest $request): array
    {
        return [];
    }

    public function instructions(RestRequest $request): array
    {
        return [];
    }
}
