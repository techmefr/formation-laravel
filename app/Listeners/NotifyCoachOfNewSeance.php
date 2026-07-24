<?php

namespace App\Listeners;

use App\Events\SeanceCreated;
use App\Notifications\SeanceCreatedNotification;
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
