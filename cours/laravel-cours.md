# Laravel, le cours complet

> **Formation StackTim · Back Laravel** — Tout ce qu'il faut comprendre pour construire l'appli « Séances de sport » : du cycle d'une requête aux events, en passant par Eloquent, l'API REST et les conventions maison XEFI.

**Stack :** PHP 8.4 · Laravel Sail · Eloquent · Spatie · JWT/lomkit · conventions XEFI

## Sommaire

1. [C'est quoi Laravel](#module-01--cest-quoi-laravel-au-juste)
2. [Environnement & Sail](#module-02--environnement-docker--laravel-sail)
3. [Cycle d'une requête](#module-03--le-cycle-de-vie-dune-requête)
4. [Routing](#module-04--le-routing)
5. [Controllers & validation](#module-05--controllers-requests--validation)
6. [Eloquent & la base](#module-06--eloquent--la-base-de-données)
7. [Relations & requêtes](#module-07--relations--requêtes)
8. [Auth & permissions](#module-08--authentification--permissions)
9. [Events, jobs, queues](#module-09--events-listeners-jobs--queues)
10. [Notifications & mail](#module-10--notifications--mail)
11. [Cache & optimisation](#module-11--cache--commandes-doptimisation)
12. [API REST & JWT](#module-12--partie-ii--api-rest--jwt)
13. [Conventions XEFI](#module-13--les-conventions-xefi-en-un-coup-dœil)

---

## Module 01 — C'est quoi Laravel, au juste

Laravel est un **framework PHP** créé par **Taylor Otwell** en 2011. Un framework, c'est un socle qui te fournit déjà toute la plomberie d'une application web — routing, base de données, sécurité, mails, files d'attente — pour que tu écrives surtout ta logique métier.

Il s'organise autour du patron **MVC**, qui sépare trois responsabilités :

- **Model** — les données et la logique qui va avec (une séance, un utilisateur).
- **View** — l'affichage (HTML via Blade). On l'utilise peu ici : en Partie II tu produis une API, pas des pages.
- **Controller** — le chef d'orchestre : il reçoit la requête, appelle les models, renvoie une réponse.

La philosophie de Laravel tient en un mot : **convention plutôt que configuration**. Si tu nommes tes classes et tes tables comme il l'attend, tout se branche automatiquement — un model `Seance` parle à la table `seances` sans que tu l'aies écrit nulle part.

### Artisan, ta télécommande

Chaque projet Laravel contient un fichier `artisan` : l'**interface en ligne de commande**. Tu lui donnes des ordres pour piloter l'appli sans écrire de code — générer des fichiers, gérer la base, inspecter les routes.

```bash
php artisan make:model Seance -mfsc  # model + migration + factory + seeder + controller
php artisan migrate                  # applique les migrations à la base
php artisan route:list               # liste toutes les routes
php artisan tinker                   # console interactive sur ton appli
```

> **À retenir** — Sous Sail, tu ne tapes jamais `php artisan` directement (PHP n'est pas installé sur ta machine) mais `sail artisan …` — la commande s'exécute dans le conteneur. On voit ça au module suivant.

---

## Module 02 — Environnement, Docker & Laravel Sail

Chez XEFI, on n'installe rien directement sur Windows. Tout tourne dans des conteneurs Docker identiques pour toute l'équipe. **Laravel Sail** est le petit script qui pilote ces conteneurs sans que tu aies à connaître Docker en profondeur.

### Sail vs Artisan

- **Sail** gère la *boîte* — l'environnement Docker autour de ton appli (PHP, MySQL, Redis, Mailpit).
- **Artisan** gère ce qu'il y a *dans* la boîte — ton application Laravel.

```bash
sail up -d            # démarre les conteneurs en arrière-plan
sail ps               # ce qui tourne
sail artisan migrate  # une commande artisan, mais DANS le conteneur
sail composer require spatie/laravel-permission
sail down             # arrête tout
```

L'alias `sail` remplace le long `./vendor/bin/sail`. Les services que Sail démarre pour ton projet : le serveur PHP, **MySQL** (la base), **Redis** (cache/queues) et **Mailpit** (capture les mails en local, sans SMTP réel).

### La structure d'un projet

| Emplacement | Ce qu'on y trouve |
|---|---|
| `app/` | Ton code : `Models/`, `Http/Controllers/`, `Listeners/`, `Notifications/`… |
| `routes/` | `web.php` (Partie I) et `api.php` (Partie II) |
| `database/` | `migrations/`, `factories/`, `seeders/` |
| `config/` | Fichiers de config PHP qui lisent le `.env` |
| `.env` | Config par environnement (identifiants, clés). **Jamais commité.** |
| `composer.json` | Le manifeste des **dépendances PHP** (l'équivalent de `package.json`) |

> ⚠️ **Piège** — `composer.json` ne compile rien et ne gère pas les routes — il déclare uniquement les dépendances. `composer install` les télécharge dans `vendor/` (dossier jamais commité non plus).

---

## Module 03 — Le cycle de vie d'une requête

Comprendre ce trajet, c'est comprendre où ton code s'insère. Toute requête HTTP traverse le même pipeline :

```
Service Providers → Middleware → Routing → Controller → Response
```

1. **Service Providers** — le démarrage. Laravel « bootstrap » l'appli : il enregistre et démarre tous les services (base, auth, tes packages). C'est **le point central de configuration**.
2. **Middleware** — des filtres qui entourent la requête : « l'utilisateur est-il connecté ? » (`auth`), a-t-il la permission ?, CORS, throttling. Une requête peut être stoppée ici avant d'atteindre ton code.
3. **Routing** — Laravel associe l'URL + la méthode HTTP (`GET /seances`) à une route, donc à un controller.
4. **Controller** — ton code s'exécute : il lit/écrit via les models et prépare la réponse.
5. **Response** — la réponse repart en sens inverse à travers les middleware jusqu'au client.

> ⚠️ **Piège de QCM** — Beaucoup inversent Middleware et Service Providers. Non : sans les providers bootstrapés, rien n'existe encore. **Providers d'abord.**

---

## Module 04 — Le routing

Une route relie une URL à un morceau de code. Tu les déclares dans `routes/web.php` (pages web, avec sessions/cookies) ou `routes/api.php` (API, sans état, préfixées par `/api`).

```php
use App\Http\Controllers\SeanceController;

Route::get('/seances', [SeanceController::class, 'index']);
Route::post('/seances', [SeanceController::class, 'store']);
Route::get('/seances/{seance}', [SeanceController::class, 'show']);

// Un seul appel pour tout le CRUD (index, create, store, show, edit, update, destroy)
Route::resource('seances', SeanceController::class);

// Regrouper des routes derrière un middleware
Route::middleware('auth')->group(function () {
    Route::resource('seances', SeanceController::class);
});
```

`{seance}` est un **paramètre de route**. Grâce au *route model binding*, si tu tapes ton paramètre en `Seance $seance` dans le controller, Laravel va chercher la séance en base tout seul (et renvoie un 404 si elle n'existe pas).

`Route::resource` génère d'un coup les 7 routes CRUD standard : c'est la façon idiomatique de câbler un CRUD complet.

---

## Module 05 — Controllers, requests & validation

Le controller reçoit la requête et orchestre la réponse. Règle d'or maison : il reste **mince** — il valide, délègue, renvoie. La logique lourde vit ailleurs (models, actions, listeners).

```php
// app/Http/Controllers/SeanceController.php
class SeanceController extends Controller
{
    public function index()
    {
        return Seance::with('coach')->latest()->paginate(15);
    }

    public function store(StoreSeanceRequest $request): Seance
    {
        // $request->validated() = uniquement les champs validés
        return Seance::create($request->validated());
    }

    public function show(Seance $seance): Seance  // route model binding
    {
        return $seance->load('coach', 'participants');
    }
}
```

### La validation via Form Request

Plutôt que valider dans le controller, on isole les règles dans une classe **Form Request** (`sail artisan make:request StoreSeanceRequest`). Elle valide *et* autorise avant même d'entrer dans le controller.

```php
// app/Http/Requests/StoreSeanceRequest.php
public function authorize(): bool
{
    return $this->user()->can('create seances');
}

public function rules(): array
{
    return [
        'name'             => ['required', 'string', 'max:255'],
        'coach_id'         => ['required', 'exists:users,id'],
        'started_at'       => ['required', 'date', 'after:now'],
        'max_participants' => ['nullable', 'integer', 'min:1'],
    ];
}
```

> **Pourquoi c'est mieux** — Les règles sont testables et réutilisables, le controller reste lisible, et si la validation échoue Laravel renvoie automatiquement une réponse 422 avec les erreurs (parfait pour une API).

---

## Module 06 — Eloquent & la base de données

Eloquent est l'**ORM** de Laravel : il te laisse manipuler la base avec des objets PHP au lieu d'écrire du SQL. Un **model** = une table.

### Les migrations : ton schéma versionné

Une migration décrit la structure d'une table en PHP. Toute l'équipe applique les mêmes et obtient le même schéma — versionné dans Git.

```php
// database/migrations/…_create_seances_table.php
Schema::create('seances', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('coach_id')->constrained('users');
    $table->dateTime('started_at');
    $table->unsignedInteger('max_participants')->nullable();
    $table->softDeletes();   // ajoute la colonne deleted_at
    $table->timestamps();    // created_at + updated_at
});
```

### Le model

```php
// app/Models/Seance.php
class Seance extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'coach_id', 'started_at', 'max_participants'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
```

`$fillable` autorise l'assignation de masse (`create([...])`) sur ces colonnes. `casts()` convertit automatiquement — `started_at` devient un objet date Carbon plutôt qu'une chaîne.

### firstOrCreate & la famille

`firstOrCreate()` cherche une ligne ; si elle n'existe pas, il la crée. Dans les deux cas tu récupères **un model**. Idéal pour le seeding (pas de doublon).

```php
User::firstOrCreate(
    ['email' => 'coach@xefi.fr'],   // critère de recherche
    ['name' => 'Coach Yoga']        // valeurs si création
);
// firstOrNew  → ne sauvegarde pas
// updateOrCreate → met à jour si trouvé (vu dans l'auth Microsoft de la doc)
```

### Soft deletes & withTrashed

Un **soft delete** « supprime » sans effacer : Laravel remplit la colonne `deleted_at`. La ligne est ignorée par défaut, mais reste récupérable et auditable.

| Méthode | Effet |
|---|---|
| `Seance::all()` | exclut les supprimés (comportement par défaut) |
| `->withTrashed()` | **inclut** les soft-deleted dans le résultat |
| `->onlyTrashed()` | uniquement les supprimés |
| `$seance->restore()` | les ressuscite |

> 🔴 **Convention XEFI** — Un model en `SoftDeletes` doit être `Prunable` : prévoir un nettoyage périodique des lignes vraiment obsolètes, sinon la table gonfle indéfiniment.

---

## Module 07 — Relations & requêtes

Les relations Eloquent traduisent les liens entre tables en méthodes PHP. Pour ton projet, une séance appartient à un coach et rassemble des participants.

| Relation | Sens | Exemple projet |
|---|---|---|
| `belongsTo` | appartient à un | une séance → son coach |
| `hasMany` | possède plusieurs | un coach → ses séances |
| `belongsToMany` | plusieurs ↔ plusieurs | séances ↔ participants (table pivot) |

```php
// Model Seance
public function participants(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'seance_user')->withTimestamps();
}

// S'inscrire / se désinscrire
$seance->participants()->attach($user->id);
$seance->participants()->detach($user->id);

// Le bouton "S'inscrire" est masqué si la limite est atteinte :
$complet = $seance->participants()->count() >= $seance->max_participants;
```

> ⚠️ **Piège — le N+1** — Boucler sur des séances puis lire `$seance->coach` déclenche une requête par séance. Charge la relation d'avance avec **eager loading** : `Seance::with('coach')->get()`. La convention XEFI « pas de requêtes dans une boucle » vise exactement ça.

### Seeders & factories

Une **factory** décrit à quoi ressemble une donnée fictive ; un **seeder** l'utilise pour remplir la base. Objectif du projet : une appli entièrement testable via un seeding réaliste.

> 🔴 **Convention XEFI** — On n'utilise **pas** `fakerphp/faker` mais `xefi/faker-php`, imposé pour des données réalistes (séances, users, rôles).

---

## Module 08 — Authentification & permissions

Deux notions distinctes : l'**authentification** (qui es-tu ?) et l'**autorisation** (as-tu le droit ?). Ton projet a trois rôles — `admin`, `coach`, `collaborator` — gérés par **spatie/laravel-permission**.

| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| Admin | toutes | toutes | toutes |
| Coach | les siennes | les siennes | non |
| Collaborateur | non | non | non |

```php
$user->assignRole('coach');
$user->hasRole('admin');
$user->can('delete seances');

// Dans une route / un groupe
Route::delete('/seances/{seance}', ...)->middleware('role:admin');
```

> 🔴 **Convention XEFI** — On raisonne en **permissions**, pas en rôles, dans le code métier : les rôles ne sont qu'un regroupement de permissions. On teste `can('delete seances')`, pas `hasRole('admin')`, pour que les règles restent souples. Les permissions servent uniquement au contrôle d'accès.

---

## Module 09 — Events, listeners, jobs & queues

C'est le cœur du côté asynchrone — et c'est là que les notifications mail de ton projet se branchent. Distinction clé pour le QCM :

- **Event** — « il s'est passé quelque chose ». Écouté par un ou **plusieurs listeners** qui réagissent. C'est du pub/sub : l'émetteur ignore qui écoute.
- **Job** — « une unité de travail à faire », souvent lourde, poussée dans une **queue** pour être traitée en arrière-plan par un worker, sans faire attendre l'utilisateur.

Un **Scheduler** programme les tâches récurrentes (« tous les jours à 8h ») : un seul cron système appelle `schedule:run` chaque minute et Laravel décide quoi lancer. À ne pas confondre avec **Horizon** (tableau de bord des queues Redis) ni **Telescope** (debug, en dev/staging uniquement).

> 🔴 **Convention XEFI — cruciale** — **Pas d'Observers. Pas de `boot()` dans les models. Pas de dossier `app/Events/`.** Toute réaction au cycle de vie d'un model passe par un **Listener** branché sur un **événement Eloquent natif**, déclaré dans l'`EventServiceProvider`. C'est le point sur lequel ton référent te reprendra.

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

---

## Module 10 — Notifications & mail

Ton projet envoie des mails automatiques aux admins et coachs à la création et la suppression d'une séance. Laravel gère ça via le système de **Notifications**, qui peut livrer par mail, base de données, Slack…

```php
// app/Notifications/SeanceCreatedNotification.php
class SeanceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Seance $seance) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle séance : ' . $this->seance->name)
            ->line('Une séance vient d\'être programmée.');
    }
}
```

> **Mailpit** — Intégré à Sail, Mailpit capte tous les mails en local — pas besoin de SMTP réel. Tu ouvres son interface web (port `8025` par défaut) pour voir les mails partir. `implements ShouldQueue` envoie le mail via la queue pour ne pas ralentir la requête.

> 🔴 **Convention XEFI** — On envoie le mail **via une Notification**, pas via un Mailable brut. Et la notification est déclenchée depuis un Listener sur l'événement Eloquent (module 09), jamais depuis un Observer.

---

## Module 11 — Cache & commandes d'optimisation

Laravel compile certaines choses en un seul fichier PHP rapide, surtout utile en production.

| Commande | Ce qu'elle fait vraiment |
|---|---|
| `config:cache` | fusionne tous les `config/*.php` en un fichier optimisé |
| `route:cache` | compile toutes les routes en un fichier unique |
| `optimize:clear` | vide tous les caches (config, routes, vues…) |

> ⚠️ **Piège majeur — .env & config:cache** — Une fois le config cache actif, Laravel **ne relit plus le `.env`** à l'exécution : `env()` hors des fichiers `config/` renverra `null`. Règle d'or : `env()` uniquement dans `config/`, partout ailleurs tu appelles `config('services.microsoft.client_id')`.

### Le cache applicatif

Stocker un résultat coûteux pour ne pas le recalculer :

```php
cache()->remember('seances_actives', 600, fn () => Seance::actives()->get());
// remember → expire après un TTL (ici 600 s)

cache()->rememberForever('roles', fn () => Role::all());
// rememberForever → n'expire JAMAIS (à invalider à la main avec forget())
```

La différence tient en un point : `remember()` prend une durée de vie, `rememberForever()` n'expire jamais.

---

## Module 12 — Partie II — API REST & JWT

La Partie II transforme l'appli en **API REST** sécurisée par JWT, pour permettre une intégration frontend future. Mêmes règles métier (rôles, permissions, notifications), mais sans sessions : chaque requête porte un **token**.

### JWT avec tymon/jwt-auth

Un **JWT** (JSON Web Token) est un jeton signé que le client renvoie à chaque requête dans l'en-tête `Authorization: Bearer <token>`. Le serveur le vérifie sans stocker de session — l'API reste *stateless*.

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/seances/{seance}/inscription', [InscriptionController::class, 'store']);
    // … les endpoints protégés
});
```

### Industrialisation avec lomkit/laravel-rest-api

Plutôt que d'écrire à la main chaque endpoint CRUD, **lomkit/laravel-rest-api** génère une API riche (filtres, tri, pagination, relations, opérations en masse) à partir d'une classe *Resource*. C'est le standard StackTim.

> **L'enveloppe search / mutate** — lomkit expose des endpoints `search` et `mutate` où le client décrit sa requête en JSON (filtres, scopes, includes). Tu déclares côté serveur ce qui est autorisé — champs filtrables, relations exposées, permissions — dans la Resource. C'est cette couche que tu configureras plutôt que d'écrire 20 controllers.

---

## Module 13 — Les conventions XEFI en un coup d'œil

Ces règles sont non négociables et notées en revue. Garde-les sous les yeux pendant toute la formation.

| Sujet | Règle |
|---|---|
| Observers | interdits → Listener sur événement Eloquent natif |
| `boot()` dans les models | interdit → Listener dans l'EventServiceProvider |
| Dossier `app/Events/` | interdit → pas de classe Event custom |
| EventServiceProvider | point central de tous les bindings événementiels |
| Permissions | `spatie/laravel-permission` — on raisonne permissions, pas rôles |
| Médias | `spatie/laravel-medialibrary` (MinIO/RustFS S3) |
| Logs / audit | `spatie/laravel-activitylog` |
| Slugs | `spatie/laravel-sluggable` |
| Seeding | `xefi/faker-php` (pas fakerphp/faker) |
| Auth API | `tymon/jwt-auth` |
| API | `lomkit/laravel-rest-api` |
| Analyse statique | Larastan niveau 5 minimum |
| Style de code | Laravel Pint avec `pint.json` à la racine |
| Debug | Telescope en dev/staging uniquement · Pulse en prod |

> 🔴 **Le fil rouge** — Retiens le principe derrière tout ça : **rendre les effets de bord explicites et testables**. Un Observer cache le comportement dans une classe résolue implicitement ; un Listener déclaré dans l'EventServiceProvider le rend visible et unitairement testable. C'est la logique de toutes les conventions.

---

*Laravel — le cours. Formation d'intégration StackTim · projet XEFI Santé Sport. Réf. doc.stacktim.com · conventions Laravel.*
