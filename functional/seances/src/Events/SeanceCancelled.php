<?php

namespace Functional\Seances\Events;

use Functional\Seances\Models\Seance;
use Illuminate\Foundation\Events\Dispatchable;

class SeanceCancelled
{
    use Dispatchable;

    public function __construct(public Seance $seance) {}
}
