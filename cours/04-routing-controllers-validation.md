# Cours 4 — Routing, Controllers & validation

> Le trajet requête → réponse, concrètement. C'est là que tu passeras le plus de temps.

## 1. Les routes

Une route relie une URL + méthode HTTP à un morceau de code. Deux fichiers :
- `routes/web.php` — pages web (sessions, cookies).
- `routes/api.php` — API sans état, préfixé automatiquement par `/api`.

```php
use App\Http\Controllers\SeanceController;

Route::get('/seances', [SeanceController::class, 'index']);
Route::post('/seances', [SeanceController::class, 'store']);
Route::get('/seances/{seance}', [SeanceController::class, 'show']);
```

`{seance}` est un **paramètre de route** (comme `[id].vue` en Nuxt ou `:id` en Express).

## 2. Route model binding — la magie utile

Si tu types le paramètre `Seance $seance` dans le controller, Laravel **va chercher la séance en base tout seul** à partir de `{seance}`, et renvoie un **404** automatiquement si elle n'existe pas.

```php
// Route : /seances/{seance}
public function show(Seance $seance)   // Laravel fait Seance::findOrFail($id) pour toi
{
    return $seance;
}
```

Plus besoin d'écrire `findOrFail` partout. C'est un des gros gains de confort.

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

Grouper derrière un middleware :

```php
Route::middleware('auth')->group(function () {
    Route::resource('seances', SeanceController::class);
});
```

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

> 💡 Renvoyer un model ou une Collection depuis un controller → Laravel le **sérialise en JSON** automatiquement. Idéal pour une API.

## 5. La validation via Form Request

Plutôt que valider dans le controller, on isole les règles dans une classe **Form Request**. C'est ton `zod`/`yup` côté serveur, sauf qu'elle **valide ET autorise** avant même d'entrer dans le controller.

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

Tu la « branches » juste en la typant en paramètre (`store(StoreSeanceRequest $request)`). Si la validation échoue, Laravel renvoie **automatiquement un 422** avec les erreurs formatées — parfait pour un front.

**Pourquoi c'est mieux** : règles testables et réutilisables, controller lisible, autorisation centralisée.

```php
$request->validated();  // uniquement les champs validés (pas de surprise)
$request->input('name'); // un champ brut si besoin
```

---

## À retenir

- Routes dans `routes/web.php` / `routes/api.php` ; `{param}` = paramètre.
- **Route model binding** : type `Seance $seance` → récupération + 404 automatiques.
- `Route::resource` / `apiResource` = tout le CRUD en une ligne.
- Controller **mince** : valide, délègue, renvoie (JSON auto).
- **Form Request** = validation + autorisation isolées, 422 auto en cas d'échec.

➡️ Suite : [Cours 5 — Relations Eloquent & le piège N+1](05-relations-eloquent.md)
