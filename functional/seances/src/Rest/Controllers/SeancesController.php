<?php

namespace Functional\Seances\Rest\Controllers;

use Functional\Seances\Rest\Resources\SeanceResource;
use Lomkit\Rest\Http\Resource;
use Technical\RestApi\Http\Controllers\Controller;

class SeancesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = SeanceResource::class;
}
