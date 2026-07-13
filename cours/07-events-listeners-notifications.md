# Cours 7 — Events, Listeners, Notifications & mail

> Le côté asynchrone, et l'endroit où les mails de ton projet se branchent. Contient **la** convention XEFI sur laquelle ton référent te reprendra. Tu connais déjà les `EventEmitter` et BullMQ — on ne fait que traduire les réflexes, une notion à la fois.

---

## 0. Le kit de survie (à lire en premier)

Trois idées suffisent à comprendre 90 % du chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **Event = « il s'est passé X » / Listener = la réaction** | Une séance est créée → c'est l'**event**. Envoyer le mail → c'est le **Listener**. L'émetteur ignore qui écoute (pub/sub). |
| **Chez XEFI, un mail part TOUJOURS d'un Listener sur événement Eloquent** | Jamais d'un Observer, jamais de `boot()`. Le model est créé en base → Eloquent émet un événement natif → un Listener déclaré dans l'`EventServiceProvider` réagit et envoie la Notification. |
| **`ShouldQueue` = en arrière-plan** | Une Notification en `ShouldQueue` ne bloque pas la requête HTTP : elle part dans la queue, un worker la traite plus tard. |

Le reste, ce sont des détails autour de ces trois idées.

---

## 1. Le vocabulaire

Une notion par ligne, rien de plus :

- **Event** — « il s'est passé quelque chose » (une séance a été créée). Émis, puis écouté par un ou plusieurs **Listeners**. C'est du pub/sub : l'émetteur ignore qui écoute.
- **Listener** — réagit à un event (envoyer un mail, logger…).
- **Job** — une unité de travail lourde, poussée dans une **queue** pour être traitée en arrière-plan par un *worker*, sans faire attendre l'utilisateur.
- **Queue** — la file d'attente (Redis chez toi) où s'empilent jobs et notifications différés.

> 💡 Traduction JS : un Event ≈ un `EventEmitter`, un Listener ≈ un handler, une Queue ≈ BullMQ. Si tu as déjà poussé des jobs dans BullMQ pour ne pas bloquer une requête, tu as déjà le bon modèle mental.

À ne pas confondre (des outils voisins, mais différents) :

- **Scheduler** — tâches récurrentes (« tous les jours à 8h »). Un seul cron système appelle `schedule:run` chaque minute, Laravel décide quoi lancer.
- **Horizon** — dashboard des queues Redis.
- **Telescope** — outil de debug (dev/staging uniquement).

---

## 2. 🔴 LA convention XEFI (cruciale)

C'est LE point du cours. Lis-le deux fois.

> **Pas d'Observers. Pas de `boot()` dans les models. Pas de dossier `app/Events/` custom.**
>
> Toute réaction au cycle de vie d'un model passe par un **Listener** branché sur un **événement Eloquent natif**, déclaré dans l'`EventServiceProvider`.

Pourquoi ? **Rendre les effets de bord explicites et testables.** Un Observer cache le comportement dans une classe résolue implicitement ; un Listener déclaré dans l'`EventServiceProvider` le rend visible et unitairement testable. C'est le fil rouge de toutes les conventions XEFI.

Eloquent émet des événements natifs à chaque étape : `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`, `restored`… On écoute ceux-là.

---

## 3. Brancher un Listener sur un événement Eloquent

Deux fichiers : **où** on écoute (le provider), et **quoi** on exécute (le listener).

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

> 💡 Le tableau `$listen` est l'annuaire « quel event → quel(s) handler(s) ». C'est l'équivalent d'un `emitter.on('created', handler)`, mais centralisé et déclaratif : d'un coup d'œil tu vois tout ce qui se déclenche.

---

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
- `public Seance $seance` dans le constructeur = **property promotion** (raccourci PHP 8 : déclare + assigne la propriété d'un coup — comme le `constructor(private readonly x)` de NestJS, vu au Cours 1).

> 💡 `via()` liste les canaux : ici `['mail']`. Tu pourrais y ajouter `'database'` ou `'slack'` sans toucher au reste — la même Notification part sur plusieurs canaux d'un coup.

---

## 5. Mailpit — voir les mails en local

Intégré à Sail, **Mailpit** capte tous les mails en local (pas de SMTP réel). Interface web sur le port **8025** : tu ouvres `http://localhost:8025` et tu vois les mails partir.

> 🔴 **Convention XEFI** : on envoie le mail **via une Notification**, jamais via un Mailable brut. Et le déclenchement vient d'un **Listener sur événement Eloquent** (section 3), jamais d'un Observer.

---

## 6. Faire tourner la queue

Comme les notifications sont en `ShouldQueue`, il faut un worker qui dépile :

```bash
sail artisan queue:work     # traite les jobs/notifs en attente
```

> 💡 Tant que ce worker ne tourne pas, la Notification est bien empilée… mais personne ne la dépile, donc aucun mail ne part. C'est exactement comme un worker BullMQ que tu aurais oublié de lancer.

---

## À retenir

- Event = « il s'est passé X » ; Listener = réaction ; Job/Queue = travail lourd en arrière-plan.
- 🔴 **XEFI** : jamais d'Observer / `boot()` / `app/Events/` — un **Listener sur événement Eloquent natif**, déclaré dans l'`EventServiceProvider`.
- **Notification** (`ShouldQueue`) pour les mails, déclenchée depuis le Listener. Jamais de Mailable brut.
- **Mailpit** (port 8025) pour lire les mails en dev ; `queue:work` pour dépiler.

## ⚠️ Les pièges qui piquent au début

1. **Oublier de lancer `queue:work`** et croire que le mail ne part pas. Il est en réalité empilé dans la queue, en attente : sans worker, rien ne se dépile. Lance `sail artisan queue:work` et regarde-le arriver.
2. **Le réflexe Observer / `boot()`** vu ailleurs : chez XEFI c'est interdit (section 2). Toute réaction au cycle de vie passe par un **Listener sur événement Eloquent natif** déclaré dans l'`EventServiceProvider`. C'est le point sur lequel ton référent te reprendra.
3. **Ne pas voir ses mails faute d'ouvrir Mailpit.** En local aucun mail ne part vers un vrai SMTP : tout est capté par Mailpit, sur `http://localhost:8025` (port **8025**). Si tu ne l'ouvres pas, tu crois à tort que rien ne fonctionne.
4. **Envoyer un Mailable brut** au lieu d'une Notification : c'est contraire à la convention XEFI (section 5). Le mail passe toujours par une **Notification**.

➡️ Suite : [Cours 8 — API REST, JWT & lomkit (Partie II)](08-api-rest-jwt-lomkit.md)
