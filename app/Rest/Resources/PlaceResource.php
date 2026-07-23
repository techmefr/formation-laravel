<?php

namespace App\Rest\Resources;

use App\Models\Place;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

class PlaceResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Place::class;

    /**
     * The exposed fields that could be provided
     *
     * @param  RestRequest  $request
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'type',
            'code',
        ];
    }

    /**
     * The exposed relations that could be provided
     *
     * @param  RestRequest  $request
     */
    public function relations(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed scopes that could be provided
     *
     * @param  RestRequest  $request
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided
     *
     * @param  RestRequest  $request
     */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /**
     * The actions that should be linked
     *
     * @param  RestRequest  $request
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked
     *
     * @param  RestRequest  $request
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }
}
