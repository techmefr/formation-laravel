<?php

namespace Functional\Seances\Listeners;

use Functional\Seances\Events\SeanceCreated;
use Functional\Seances\Notifications\SeanceCreatedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyCoachOfNewSeance
{
    public function handle(SeanceCreated $event): void
    {
        $coach = $event->seance->coach;

        if ($coach !== null) {
            Notification::send($coach, new SeanceCreatedNotification($event->seance));
        }
    }
}
