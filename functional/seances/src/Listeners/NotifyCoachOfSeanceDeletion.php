<?php

namespace Functional\Seances\Listeners;

use Functional\Seances\Events\SeanceDeleted;
use Functional\Seances\Notifications\SeanceDeletedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyCoachOfSeanceDeletion
{
    public function handle(SeanceDeleted $event): void
    {
        $coach = $event->seance->coach;

        if ($coach !== null) {
            Notification::send($coach, new SeanceDeletedNotification($event->seance));
        }
    }
}
