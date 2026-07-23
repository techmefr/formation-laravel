<?php

namespace App\Listeners;

use App\Events\SeanceCancelled;
use App\Notifications\SeanceCancelledNotification;
use Illuminate\Support\Facades\Notification;

class NotifyParticipantsOfCancellation
{
    public function handle(SeanceCancelled $event): void
    {
        Notification::send(
            $event->seance->participants,
            new SeanceCancelledNotification($event->seance)
        );
    }
}
