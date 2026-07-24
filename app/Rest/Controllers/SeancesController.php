<?php

namespace App\Rest\Controllers;

use App\Rest\Resources\SeanceResource;
use Lomkit\Rest\Http\Resource;

class SeancesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = SeanceResource::class;
}
