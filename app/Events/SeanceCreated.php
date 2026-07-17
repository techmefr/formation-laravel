<?php

namespace App\Events;

use App\Models\Seance;
use Illuminate\Foundation\Events\Dispatchable;

class SeanceCreated
{
    use Dispatchable;

    public function __construct(public Seance $seance) {}
}
