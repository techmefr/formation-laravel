# Cours 4 — Routing, Controllers & validation

> Objectif : suivre le **trajet complet d'une requête → réponse** dans Laravel, sans te perdre. Tu connais déjà ce circuit (routes, controllers, validation) côté Nest/Nuxt — on ne fait que traduire les réflexes, une notion à la fois. C'est là que tu passeras le plus de temps.

---

## 0. Le kit de survie (à lire en premier)

Avant le détail, trois idées suffisent à comprendre tout le chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **La route pointe vers une méthode de controller** | `Route::get('/seances', [SeanceController::class, 'index'])` = « cette URL déclenche cette méthode ». Comme un décorateur `@Get()` sur une méthode de controller NestJS, mais écrit dans un fichier de routes à part. |
| **Le controller reste mince : valide, délègue, renvoie** | Il ne contient pas la logique métier lourde. Il vérifie l'entrée, appelle le model/une action, et renvoie une réponse (souvent du JSON). |
| **La validation vit dans une classe à part (Form Request)** | Un peu comme un schéma `zod`/`yup`, sauf qu'elle **valide ET autorise** l'appel *avant* même d'entrer dans le controller. |

Le reste, ce sont des raccourcis pratiques autour de ces trois idées.

---

## 1. Les routes

Une route relie une **URL + une méthode HTTP** à un morceau de code. Elle vit dans un fichier de routes :
- `routes/web.php` — pages web (sessions, cookies). C'est le seul livré par défaut en Laravel 11+.
- `routes/api.php` — API sans état, préfixée automatiquement par `/api` (à ajouter avec `php artisan install:api`).

> On détaille pourquoi ces fichiers existent, et pourquoi ce n'est **pas** une histoire de ports, en [§1 ter](#1-ter-les-fichiers-de-routes--web-api-console-rien-à-voir-avec-les-ports).

```php
use App\Http\Controllers\SeanceController;

Route::get('/seances', [SeanceController::class, 'index']);
Route::post('/seances', [SeanceController::class, 'store']);
Route::get('/seances/{seance}', [SeanceController::class, 'show']);
```

`{seance}` est un **paramètre de route**.

> 💡 `{seance}` = le segment dynamique de l'URL, comme `[id].vue` dans le routing de fichiers de Nuxt, ou `:id` dans une route Express. La différence : ici tu déclares explicitement la route et la méthode qu'elle appelle, au lieu de te reposer sur l'arborescence des fichiers.

---

## 1 bis. Folio — le routing par fichiers (ce qu'on utilise ici)

Sur ce projet, en plus des routes classiques, on utilise **Laravel Folio** : un fichier Blade dans `resources/views/pages/` **devient une route automatiquement**, exactement comme le dossier `pages/` de Nuxt.

| Fichier | URL générée |
|---|---|
| `pages/planning/index.blade.php` | `/planning` |
| `pages/seances/index.blade.php` | `/seances` |
| `pages/seances/[Seance].blade.php` | `/seances/{seance}` (route model binding inclus) |

Pas de controller à écrire : la logique et l'affichage vivent dans le même fichier. En haut de page, un bloc PHP configure la route :

```php
<?php
use App\Models\Seance;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);        // protège la page (comme un guard)
name('seances.index');       // nom de route -> route('seances.index')

$seances = Seance::with('coach')->orderBy('started_at')->get();
?>

<x-app-layout title="Séances">
    {{-- ... le HTML utilise $seances ... --}}
</x-app-layout>
```

> 💡 C'est le même modèle mental que Nuxt : l'arborescence **est** le routing. Différence avec les routes classiques (§1) : pas de fichier `web.php` ni de controller pour ces pages. Les deux approches **cohabitent** ici — Folio pour les pages qui affichent, `web.php` + controllers pour les **actions** (s'inscrire, se désinscrire) qui ont besoin de POST/DELETE nommés.

> ⚠️ **Piège Folio :** le bloc PHP du haut est évalué **aussi au démarrage** (quand Folio recense les routes), là où `auth()->user()` est encore `null`. Donc tout code qui suppose un utilisateur connecté doit être **null-safe** : `if ($user = auth()->user()) { ... }`, jamais `auth()->user()->machin()` en direct.

> 💡 **Pas de Livewire ici.** Folio ne dépend pas de Livewire. Nos pages sont du **Blade classique rendu côté serveur** : les interactions (s'inscrire, changer de semaine) passent par de simples `<form>` et des liens, pas par du JavaScript réactif. La plupart des « UI kits Blade » (Flux, WireUI, Mary) imposeraient Livewire — on les évite, on reste sur Folio + Tailwind/daisyUI.

---

## 1 ter. Les fichiers de routes : web, api, console… (rien à voir avec les ports)

Idée fausse fréquente : « web et api, c'est deux ports différents ». **Non.** Toute l'app tourne sur **un seul port** (ici `19080`). Le serveur reçoit *toutes* les requêtes dessus, et c'est **l'URL + la méthode HTTP** qui décident quelle route répond. Ce qui distingue les fichiers de routes, c'est **le groupe de middlewares** et **le préfixe d'URL** qu'on leur applique.

C'est configuré dans `bootstrap/app.php` (Laravel 11+) :

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',        // groupe "web"
        commands: __DIR__.'/../routes/console.php', // pas du HTTP : de la CLI
        health: '/up',                              // route de monitoring auto
    )
```

| Fichier | Groupe / nature | Préfixe URL | Type de client |
|---|---|---|---|
| `routes/web.php` | middleware `web` : **session, cookie, CSRF** | **aucun** (`/login`, `/seances`) | un **navigateur** avec une session |
| `routes/api.php` | middleware `api` : **stateless**, token, `throttle` | **`/api`** (`/api/login`) | un **client API** (mobile, SPA, autre serveur) qui envoie un token |
| `routes/console.php` | commandes Artisan + tâches planifiées | — | la **CLI** (`php artisan …`, cron) |
| `routes/channels.php` | autorisation des canaux WebSocket | — | le **broadcasting** temps réel |

> 💡 Analogie Nest : un seul serveur `node` qui écoute un port, mais deux `RouterModule` montés différemment — l'un avec un `SessionGuard` à la racine, l'autre avec un `JwtGuard` monté sur `/api`. Le port ne change pas ; le **chemin** et le **guard** changent.

### La vraie réponse à ta question : le préfixe

C'est **`/login` (web)** et **`/api/login` (api)** — jamais `/web/login`. Le groupe web **n'ajoute aucun préfixe**, seul le groupe api préfixe automatiquement par `/api`. Même logique, deux clients :

```php
// routes/web.php  → un navigateur qui affiche un formulaire et reçoit un cookie
Route::post('/login', [AuthController::class, 'store']);          // URL finale : /login

// routes/api.php  → un client qui envoie du JSON et reçoit un token
Route::post('/login', [Api\AuthController::class, 'store']);      // URL finale : /api/login
```

Les deux fichiers peuvent contenir **la même ligne** `Route::post('/login', …)` sans collision : Laravel préfixe le second par `/api`. Tu peux changer ce préfixe :

```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',        // → /api/v1/login
)
```

### web = session, api = stateless (le vrai critère)

- **web** : après login, Laravel pose un **cookie de session**. Le navigateur le renvoie tout seul à chaque requête → d'où la protection **CSRF**. C'est ce qu'on utilise dans tout le projet actuel.
- **api** : **aucun cookie, aucune session**. Le client renvoie un **token** (`Authorization: Bearer …`) à chaque appel. Pas de CSRF (pas de cookie automatique), mais du **rate-limiting** (`throttle`). C'est la **Partie II** (JWT + lomkit, [cours 8](08-api-rest-jwt-lomkit.md)).

### Détail Laravel 11+ : `api.php` n'existe pas par défaut

Ton projet n'a que `web.php` et `console.php` — regarde `bootstrap/app.php`, il n'y a pas de ligne `api:`. Depuis Laravel 11, on ajoute l'API quand on en a besoin :

```bash
php artisan install:api          # crée routes/api.php, installe Sanctum, ajoute la ligne api: dans withRouting()
```

### `console.php` : ce n'est pas du HTTP

Ce fichier enregistre des **commandes Artisan** et le **planificateur** (cron). Aucune URL, aucun port :

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Artisan::command('seances:rappel', function () {
    $this->info('Envoi des rappels…');   // lancé par : php artisan seances:rappel
});

Schedule::command('seances:rappel')->dailyAt('07:00');   // tâche planifiée
```

### Et « ou autre » : oui, tu peux créer tes propres fichiers de routes

Par exemple un fichier dédié aux webhooks, monté sur son propre préfixe et son propre middleware :

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    then: function () {
        Route::middleware('api')
            ->prefix('webhooks')
            ->group(base_path('routes/webhooks.php'));   // → /webhooks/stripe, etc.
    },
)
```

### Port ≠ type de route (le piège mental)

Réflexe fréquent : « l'api tourne sur 443, le web sur 80, le WebSocket sur un autre port… ». **Non.** Un **port** = une porte vers un **processus/protocole**, pas vers un type de route.

- **80 vs 443** ne séparent pas web/api : c'est **HTTP vs HTTPS** (chiffrement). En prod, ton web **et** ton api passent tous les deux par **443**. `https://site.fr/seances` et `https://site.fr/api/seances` = **même port, même processus PHP** ; c'est le **chemin** qui aiguille en interne.
- **WebSocket** : il *peut* avoir son propre port, mais parce que c'est **un autre programme** (un serveur qui garde les connexions ouvertes en continu, ex. Laravel Reverb sur `:8080`), pas parce que « c'est de l'api ». Le plus souvent il passe même par 80/443 (`ws://` / `wss://`).

Dans ton projet, chaque port = **un conteneur différent**, jamais un type de route :

| Port | Processus | Rôle |
|---|---|---|
| `19080` | conteneur `laravel.test` | **toute** l'app — web **et** (bientôt) api, même porte |
| `19306` | MySQL | base de données |
| `19825` | Mailpit | interface des mails de test |
| `19073` | Vite | serveur de dev des assets JS/CSS |

web et api **n'apparaissent pas** ici : ce ne sont pas des ports, ce sont deux groupes de routes du **même** processus `19080`. Après `install:api`, `/api/...` répondra **sur le même 19080**.

> 🎯 En une phrase : **le port choisit le *programme* (app / base / mail / websocket) ; le chemin d'URL choisit la *route* (web `/login` vs api `/api/login`) une fois dans l'app.**

---

## 2. Route model binding — la magie utile

Si tu **types** le paramètre `Seance $seance` dans le controller, Laravel **va chercher la séance en base tout seul** à partir de `{seance}`, et renvoie un **404** automatiquement si elle n'existe pas.

```php
// Route : /seances/{seance}
public function show(Seance $seance)   // Laravel fait Seance::findOrFail($id) pour toi
{
    return $seance;
}
```

Plus besoin d'écrire `findOrFail` partout : c'est un des gros gains de confort.

> 💡 Rappelle-toi le `findOrFail` du cours 3 : tu l'écrivais toi-même. Ici, le simple fait de typer le paramètre avec le model suffit à déclencher la recherche + le 404. Le nom du paramètre de route (`{seance}`) doit correspondre au nom de la variable typée (`$seance`).

---

## 3. `Route::resource` — tout le CRUD d'un coup

```php
Route::resource('seances', SeanceController::class);
```

Génère les **7 routes CRUD** standard d'un coup :

| Méthode + URL | Action controller | Rôle |
|---|---|---|
| `GET /seances` | `index` | liste |
| `GET /seances/create` | `create` | formulaire création (web) |
| `POST /seances` | `store` | enregistre |
| `GET /seances/{seance}` | `show` | détail |
| `GET /seances/{seance}/edit` | `edit` | formulaire édition (web) |
| `PUT/PATCH /seances/{seance}` | `update` | met à jour |
| `DELETE /seances/{seance}` | `destroy` | supprime |

Pour une API (pas de formulaires HTML) : `Route::apiResource(...)` → mêmes routes sans `create`/`edit`.

> 💡 Les deux routes `create` et `edit` ne servent qu'à afficher un **formulaire HTML** côté serveur. Si ton front est un SPA/Nuxt qui gère ses propres formulaires, tu n'en as pas besoin → `apiResource` est fait pour toi.

Grouper derrière un middleware :

```php
Route::middleware('auth')->group(function () {
    Route::resource('seances', SeanceController::class);
});
```

> 💡 Un **middleware** ici, c'est exactement l'idée du middleware Express : une couche qui s'exécute **avant** ton controller et peut bloquer la requête (ici, `auth` refuse l'accès si l'utilisateur n'est pas connecté). Le `->group(...)` applique la même couche à toutes les routes du bloc, comme un `router.use(auth)` qui couvre un ensemble de routes.

---

## 3 bis. Brancher les middlewares sur les routes

Un middleware, c'est une **couche qui s'exécute avant le controller** et peut bloquer la requête (guard Nest / middleware Express). La vraie question, c'est **comment on l'accroche** à une route. Il y a trois niveaux, et dans ton projet ils sont déjà tous là.

### Niveau 0 — le groupe automatique (tu ne l'écris pas)

Chaque route de `web.php` reçoit **automatiquement** le groupe `web` (session, cookie, CSRF), parce que le fichier est monté via `web:` dans `bootstrap/app.php`. Idem `api.php` → groupe `api`. C'est le socle, tu n'écris rien.

### Niveau 1 — un groupe de routes (ce que tu utilises)

```php
Route::middleware('guest')->group(function () {   // seulement si PAS connecté
    Route::get('/login', ...)->name('login');
});

Route::middleware('auth')->group(function () {    // seulement si connecté
    Route::post('/seances', ...)->name('seances.store');
    Route::post('/logout', ...)->name('logout');
});
```

La requête traverse le middleware **avant** d'atteindre le controller ; s'il refuse, le controller n'est jamais appelé.

### Niveau 2 — une seule route (et on peut cumuler)

```php
Route::post('/seances', [SeanceController::class, 'store'])
    ->middleware(['auth', 'verified'])       // plusieurs, dans l'ordre
    ->name('seances.store');
```

### Middleware avec paramètre (le `:`)

Certains prennent des arguments après `:` :

```php
->middleware('can:create,App\Models\Seance')   // autorisation via policy
->middleware('throttle:60,1')                  // 60 requêtes / minute
```

> Chez toi, l'autorisation (`create/update/…`) est faite dans les **Form Request** (`authorize()`) + les **Policies**, pas via `can:` sur la route. Les deux sont valides — tu as choisi la Form Request.

### D'où viennent les noms `auth`, `guest`, `can`, `throttle` ?

Ce sont des **alias**. Laravel en fournit par défaut (`auth`, `guest`, `verified`, `throttle`, `can`, `signed`…). Pour brancher **ton** middleware, tu lui donnes un alias dans `bootstrap/app.php` :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'agency' => \App\Http\Middleware\EnsureSameAgency::class,  // → ->middleware('agency')
    ]);
    $middleware->appendToGroup('web', \App\Http\Middleware\Xxx::class); // à TOUTES les routes web
})
```

### Cas Folio (tes pages)

Pas de ligne `Route::` : tu déclares le middleware **en tête de la page** :

```php
<?php
use function Laravel\Folio\middleware;
middleware(['auth']);        // même effet que ->middleware('auth')
```

### L'ordre compte — le modèle « oignon »

Les middlewares s'empilent. La requête les traverse de l'extérieur vers le controller, puis la réponse **ressort en sens inverse** :

```
requête → [web: session] → [auth] → [throttle] → CONTROLLER → réponse (ressort par les mêmes couches)
```

Si `auth` bloque, on n'atteint jamais `throttle` ni le controller.

### Récap — où accrocher un middleware

| Portée | Syntaxe | Dans ton projet |
|---|---|---|
| Tout un fichier | `web:` / `api:` dans `bootstrap/app.php` | groupe `web` auto |
| Un groupe de routes | `Route::middleware('auth')->group(...)` | ✅ blocs `guest` / `auth` |
| Une route | `->middleware(['auth', 'verified'])` | possible |
| Avec paramètre | `->middleware('can:create,App\Models\Seance')` | via Form Request chez toi |
| Une page Folio | `middleware(['auth'])` en tête de page | ✅ |
| Global / custom | `alias(...)`, `append(...)` dans `bootstrap/app.php` | pas encore utilisé |

---

## 4. Le controller reste mince

Règle d'or maison : il **valide, délègue, renvoie**. La logique lourde vit ailleurs (models, actions, listeners).

```php
// app/Http/Controllers/SeanceController.php
class SeanceController extends Controller
{
    public function index()
    {
        return Seance::with('coach')->latest()->paginate(15);
        // ->with('coach') = eager loading (cf. cours 5, anti N+1)
        // ->paginate(15)  = pagination auto (renvoie data + méta)
    }

    public function store(StoreSeanceRequest $request): Seance
    {
        return Seance::create($request->validated());  // uniquement les champs validés
    }

    public function show(Seance $seance): Seance
    {
        return $seance->load('coach', 'participants');
    }
}
```

> 💡 Renvoyer un model ou une Collection depuis un controller → Laravel le **sérialise en JSON** automatiquement. Tu n'as pas à faire un `res.json(...)` explicite comme en Express : tu `return` l'objet, et c'est déjà du JSON. Idéal pour une API.

Remarque le paramètre `StoreSeanceRequest $request` dans `store()` : ce n'est pas la requête brute, c'est une classe de validation. On y arrive tout de suite.

---

## 5. La validation via Form Request

Plutôt que valider dans le controller, on isole les règles dans une classe **Form Request**.

> 💡 C'est ton `zod`/`yup` **côté serveur**, avec deux différences importantes : (1) c'est une classe dédiée, pas un schéma inline ; (2) elle **valide ET autorise** l'appel *avant* même d'entrer dans le controller. Tu n'as donc plus à faire `schema.parse(body)` en première ligne de ta méthode : c'est fait en amont.

```bash
sail artisan make:request StoreSeanceRequest
```

```php
// app/Http/Requests/StoreSeanceRequest.php
class StoreSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create seances');   // autorisation (cf. cours 6)
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'coach_id'         => ['required', 'exists:users,id'],   // doit exister en base
            'started_at'       => ['required', 'date', 'after:now'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
```

Deux méthodes à retenir dans cette classe :
- **`authorize()`** — répond « cet utilisateur a-t-il le droit de faire cet appel ? » (on creusera les autorisations au cours 6).
- **`rules()`** — la liste des règles, une entrée par champ. `exists:users,id` va même vérifier en base que la valeur existe.

Tu la « branches » juste en la **typant en paramètre** (`store(StoreSeanceRequest $request)`). Si la validation échoue, Laravel renvoie **automatiquement un 422** avec les erreurs formatées — parfait pour un front.

> 💡 Le 422 automatique, c'est le gros confort : pas de `if (!result.success) return res.status(422)...` à écrire à la main. Tu déclares les règles, Laravel s'occupe de la réponse d'erreur et de son format.

**Pourquoi c'est mieux** : règles testables et réutilisables, controller lisible, autorisation centralisée.

```php
$request->validated();  // uniquement les champs validés (pas de surprise)
$request->input('name'); // un champ brut si besoin
```

> 💡 Réflexe sécurité (dans l'esprit du `$fillable` du cours 3) : passe `$request->validated()` à `create()`, **pas** `$request->all()`. Tu n'écris ainsi que les champs que tu as explicitement validés.

---

## 5 bis. Form Request vs validation « inline » — ça change quoi ?

On aurait pu tout écrire **dans le controller**. Comparons, parce que le comportement final est le même — ce qui change, c'est le rangement et la robustesse.

**Ce qu'on écrit naturellement (inline dans le controller) :**

```php
public function store(Request $request)
{
    if (! $request->user()->can('create', Seance::class)) {
        abort(403);                                   // autorisation à la main
    }

    $data = $request->validate([                       // validation à la main
        'name' => ['required', 'string', 'max:255'],
        'started_at' => ['required', 'date'],
        // ...
    ]);

    if ($request->user()->hasRole('coach')) {          // règle métier à la main
        $data['coach_id'] = $request->user()->id;
    }

    $this->seances->create($data);
}
```

**Avec une Form Request :**

```php
class StoreSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Seance::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()->hasRole('coach')) {
            $this->merge(['coach_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], /* ... */];
    }
}

// controller : une ligne
public function store(StoreSeanceRequest $request)
{
    $this->seances->create($request->validated());     // déjà validé + autorisé
}
```

La Form Request fait **deux choses, automatiquement, AVANT le controller** : `authorize()` (le droit) puis `rules()` (la validation).

| | Inline (dans le controller) | Form Request |
|---|---|---|
| Où vit la logique | mélangée dans la méthode | classe dédiée |
| Quand ça tourne | tu l'écris toi, en 1re ligne | **avant** le controller, tout seul |
| Si non autorisé | tu dois penser à `abort(403)` | `authorize()` false → **403 auto** |
| Si validation KO | `validate()` lève l'erreur | **redirect back + erreurs + `old()`** auto |
| Réutilisation | à recopier dans `store` ET `update` | règles centralisées par action |
| Testabilité | il faut tester tout le controller | autorisation **testée seule** (`assertForbidden`) |
| Risque d'oubli | on peut zapper le check → trou de sécu | check **structurel**, impossible à oublier |

> 💡 Résultat identique côté utilisateur, mais le controller redevient ce qu'on veut — **valide, délègue, renvoie** — et la sécurité ne dépend plus de « penser à » l'écrire.

### Le vrai déclencheur sur ce projet

À un moment on avait mis l'`abort(403)` **dans la page Folio**. Problème : le bloc PHP d'une page Folio s'exécute aussi **au recensement des routes** (cf. §1 bis), quand `auth()->user()` est encore `null` → **403 partout**. La correction :

- l'**autorisation de l'action** va dans la **Form Request** (`authorize()`) → sécurise réellement le `POST`/`PUT` ;
- un simple `@can` dans le Blade → uniquement pour **afficher/masquer le bouton**.

Autrement dit, la Form Request n'a pas seulement rangé le code : elle a remis l'autorisation **au bon endroit** (sur l'action, pas sur l'affichage de la page).

---

## 6. La couche Service (comme ton front)

Le controller reste **mince** et délègue la logique à une classe **Service**, exactement comme une page front appelle un `authService.ts`.

- **Front** : page/composant → `authService.ts` → API
- **Back** : Route → Controller → `AuthService` → Model (BDD)

Laravel injecte le service tout seul dans le constructeur du controller (**injection de dépendances**) :

```php
class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function store(Request $request)
    {
        $data = $request->validate([...]);
        return $this->auth->register($data);   // logique déléguée
    }
}
```

> 💡 Tu connais déjà le pattern : côté Nest, tu injectes un service dans le constructeur d'un controller et le framework le résout tout seul. Ici c'est pareil — le simple fait de typer `private AuthService $auth` suffit, Laravel fabrique et injecte l'instance. Le controller ne fait que valider et appeler la bonne méthode du service.

---

## 7. Politique de mot de passe (règle dédiée, pas de regex)

En Laravel on n'écrit **pas de regex à la main** : la règle `Password` exprime les critères et donne des messages clairs. On la définit **une seule fois** (via `Password::defaults()` dans `AppServiceProvider::boot()`), puis on l'utilise partout :

```php
// AppServiceProvider::boot()
Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

// dans une validation
'password' => ['required', 'confirmed', Password::defaults()],
```

`min(12)` = 12 caractères, `mixedCase()` = 1 maj + 1 min, `numbers()` = 1 chiffre, `symbols()` = 1 spécial.

> 💡 Au lieu d'une regex illisible copiée-collée dans chaque schéma de validation, tu déclares la politique **au même endroit pour toute l'app**. Si la règle change (14 caractères demain), tu la modifies une seule fois dans `AppServiceProvider` et tout suit. Bonus : les messages d'erreur sont déjà rédigés et traduits.

---

## 8. Convention : toujours un status + un message

🔴 **Convention XEFI** : chaque réponse renvoie un **type/status** ET un **message humain clair** (prêt pour une notification côté front). Bien distinguer le **code HTTP** (200/201/422/403… pour la machine) du **message** (pour l'humain).

- **Redirection (Blade)** : `->with('notification', ['type' => 'success|error|info', 'message' => '...'])`
- **Réponse JSON (API)** : `{ "type": "success", "message": "..." }` + le bon code HTTP

> 💡 Le code HTTP parle à la machine (ton front sait qu'un 422 = validation, un 403 = interdit) ; le `message` parle à l'utilisateur et alimente directement ta notif (toast). Les deux ne font pas doublon : tu as besoin des deux à chaque réponse.

---

## À retenir

- Routes dans `routes/web.php` / `routes/api.php` ; `{param}` = paramètre.
- **Route model binding** : type `Seance $seance` → récupération + 404 automatiques.
- `Route::resource` / `apiResource` = tout le CRUD en une ligne.
- Controller **mince** : valide, délègue, renvoie (JSON auto).
- **Form Request** = validation + autorisation isolées, 422 auto en cas d'échec.

## ⚠️ Les pièges qui piquent au début

1. **Oublier de typer le paramètre du controller** (`show($seance)` au lieu de `show(Seance $seance)`) → pas de route model binding, tu récupères juste l'`id` brut et tu dois refaire le `findOrFail` toi-même. Le type, c'est ce qui déclenche la magie.
2. **Le nom du paramètre de route et celui de la variable doivent correspondre** : `/{seance}` ↔ `Seance $seance`. S'ils divergent, le binding ne se fait pas.
3. **Attendre le 422 alors que la Form Request n'est pas typée dans la méthode** : tant que tu ne l'as pas mise en paramètre (`store(StoreSeanceRequest $request)`), aucune validation ne tourne. C'est le type-hint qui la branche.
4. **Passer `$request->all()` à `create()`** au lieu de `$request->validated()` : tu réintroduis exactement le trou de sécurité que `$fillable` et la validation servent à fermer.

➡️ Suite : [Cours 5 — Relations Eloquent & le piège N+1](05-relations-eloquent.md)
