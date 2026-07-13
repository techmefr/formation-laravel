# Cours 7 — Events, Listeners, Notifications & mail

> Le côté asynchrone, et l'endroit où les mails de ton projet se branchent. Contient **la** convention XEFI sur laquelle ton référent te reprendra.

## 1. Le vocabulaire

- **Event** — « il s'est passé quelque chose » (une séance a été créée). Émis, puis écouté par un ou plusieurs **Listeners**. C'est du pub/sub : l'émetteur ignore qui écoute.
- **Listener** — réagit à un event (envoyer un mail, logger…).
- **Job** — une unité de travail lourde, poussée dans une **queue** pour être traitée en arrière-plan par un *worker*, sans faire attendre l'utilisateur.
- **Queue** — la file d'attente (Redis chez toi) où s'empilent jobs et notifications différés.

Analogies JS : un Event ≈ un `EventEmitter`, un Listener ≈ un handler, une Queue ≈ BullMQ.

À ne pas confondre :
- **Scheduler** — tâches récurrentes (« tous les jours à 8h »). Un seul cron système appelle `schedule:run` chaque minute, Laravel décide quoi lancer.
- **Horizon** — dashboard des queues Redis.
- **Telescope** — outil de debug (dev/staging uniquement).

## 2. 🔴 LA convention XEFI (cruciale)

> **Pas d'Observers. Pas de `boot()` dans les models. Pas de dossier `app/Events/` custom.**
>
> Toute réaction au cycle de vie d'un model passe par un **Listener** branché sur un **événement Eloquent natif**, déclaré dans l'`EventServiceProvider`.

Pourquoi ? **Rendre les effets de bord explicites et testables.** Un Observer cache le comportement dans une classe résolue implicitement ; un Listener déclaré dans l'`EventServiceProvider` le rend visible et unitairement testable. C'est le fil rouge de toutes les conventions XEFI.

Eloquent émet des événements natifs à chaque étape : `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`, `restored`… On écoute ceux-là.

## 3. Brancher un Listener sur un événement Eloquent

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'eloquent.created: ' . Seance::class => [NotifySeanceCreated::class],
    'eloquent.deleted: ' . Seance::class => [NotifySeanceDeleted::class],
];
```

```php
// app/Listeners/NotifySeanceCreated.php
class NotifySeanceCreated
{
    public function handle(Seance $seance): void
    {
        $destinataires = User::role(['admin', 'coach'])->get();
        Notification::send($destinataires, new SeanceCreatedNotification($seance));
    }
}
```

Résultat : dès qu'une `Seance` est créée en base (peu importe d'où), le mail part — sans une ligne dans le controller. Le comportement est déclaré à un seul endroit lisible.

## 4. Les Notifications

Une **Notification** est un message multi-canal (mail, base, Slack…). Ton projet : mail aux admins/coachs à la création et la suppression d'une séance.

```bash
sail artisan make:notification SeanceCreatedNotification
```

```php
// app/Notifications/SeanceCreatedNotification.php
class SeanceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Seance $seance) {}

    public function via($notifiable): array
    {
        return ['mail'];                     // canaux de livraison
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle séance : ' . $this->seance->name)
            ->line('Une séance vient d\'être programmée.')
            ->action('Voir la séance', url('/seances/' . $this->seance->id));
    }
}
```

- `implements ShouldQueue` → le mail part **via la queue**, sans ralentir la requête HTTP.
- `public Seance $seance` dans le constructeur = **property promotion** (raccourci PHP 8 : déclare + assigne la propriété d'un coup).

## 5. Mailpit — voir les mails en local

Intégré à Sail, **Mailpit** capte tous les mails en local (pas de SMTP réel). Interface web sur le port **8025** : tu ouvres `http://localhost:8025` et tu vois les mails partir.

> 🔴 **Convention XEFI** : on envoie le mail **via une Notification**, jamais via un Mailable brut. Et le déclenchement vient d'un **Listener sur événement Eloquent** (section 3), jamais d'un Observer.

## 6. Faire tourner la queue

Comme les notifications sont en `ShouldQueue`, il faut un worker qui dépile :

```bash
sail artisan queue:work     # traite les jobs/notifs en attente
```

---

## À retenir

- Event = « il s'est passé X » ; Listener = réaction ; Job/Queue = travail lourd en arrière-plan.
- 🔴 **XEFI** : jamais d'Observer / `boot()` / `app/Events/` — un **Listener sur événement Eloquent natif**, déclaré dans l'`EventServiceProvider`.
- **Notification** (`ShouldQueue`) pour les mails, déclenchée depuis le Listener. Jamais de Mailable brut.
- **Mailpit** (port 8025) pour lire les mails en dev ; `queue:work` pour dépiler.

➡️ Suite : [Cours 8 — API REST, JWT & lomkit (Partie II)](08-api-rest-jwt-lomkit.md)
