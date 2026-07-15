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

Une route relie une **URL + une méthode HTTP** à un morceau de code. Il y a deux fichiers :
- `routes/web.php` — pages web (sessions, cookies).
- `routes/api.php` — API sans état, préfixé automatiquement par `/api`.

```php
use App\Http\Controllers\SeanceController;

Route::get('/seances', [SeanceController::class, 'index']);
Route::post('/seances', [SeanceController::class, 'store']);
Route::get('/seances/{seance}', [SeanceController::class, 'show']);
```

`{seance}` est un **paramètre de route**.

> 💡 `{seance}` = le segment dynamique de l'URL, comme `[id].vue` dans le routing de fichiers de Nuxt, ou `:id` dans une route Express. La différence : ici tu déclares explicitement la route et la méthode qu'elle appelle, au lieu de te reposer sur l'arborescence des fichiers.

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
