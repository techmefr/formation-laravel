<?php

namespace Functional\Seances\Events;

use Functional\Seances\Models\Seance;
use Illuminate\Foundation\Events\Dispatchable;

class SeanceCreated
{
    use Dispatchable;

    public function __construct(public Seance $seance) {}
}
