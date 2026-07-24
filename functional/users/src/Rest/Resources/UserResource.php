<?php

namespace Functional\Users\Rest\Resources;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\RestApi\Http\Resources\Resource;

class UserResource extends Resource
{
    /**
     * @var class-string<Model>
     */
    public static $model = User::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'email',
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
