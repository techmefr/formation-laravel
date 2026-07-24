<?php

namespace App\Listeners;

use App\Events\SeanceDeleted;
use App\Notifications\SeanceDeletedNotification;
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
