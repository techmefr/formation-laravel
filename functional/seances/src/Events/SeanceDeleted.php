<?php

namespace Functional\Seances\Events;

use Functional\Seances\Models\Seance;
use Illuminate\Foundation\Events\Dispatchable;

class SeanceDeleted
{
    use Dispatchable;

    public function __construct(public Seance $seance) {}
}
