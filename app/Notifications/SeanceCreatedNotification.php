<?php

namespace App\Notifications;

use App\Models\Seance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeanceCreatedNotification extends Notification
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
            ->subject('Nouvelle séance : '.$this->seance->name)
            ->line('Votre séance « '.$this->seance->name.' » a bien été créée.')
            ->line('Quand : '.$this->seance->started_at->format('d/m/Y à H:i'))
            ->line('Lieu : '.$this->seance->place->name);
    }
}
