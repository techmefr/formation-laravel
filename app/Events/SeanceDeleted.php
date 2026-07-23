<?php

namespace App\Events;

use App\Models\Seance;
use Illuminate\Foundation\Events\Dispatchable;

class SeanceDeleted
{
    use Dispatchable;

    public function __construct(public Seance $seance) {}
}
