<?php

namespace Functional\Seances\Notifications;

use Functional\Seances\Models\Seance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeanceDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Seance $seance) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Séance supprimée : '.$this->seance->name)
            ->line('La séance « '.$this->seance->name.' » du '.$this->seance->started_at->format('d/m/Y à H:i').' a été supprimée.');
    }
}
