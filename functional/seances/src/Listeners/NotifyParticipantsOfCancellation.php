<?php

namespace Functional\Seances\Listeners;

use Functional\Seances\Events\SeanceCancelled;
use Functional\Seances\Notifications\SeanceCancelledNotification;
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
