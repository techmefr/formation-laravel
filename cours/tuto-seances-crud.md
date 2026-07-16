# Tuto — Le CRUD des séances

> But : comprendre **la logique** du CRUD séances (créer / lister / voir / modifier / supprimer) avec les **permissions par rôle**, avant de le construire. Toujours en **session + Blade** (comme l'auth). On réutilise : [Cours 3](03-eloquent-migrations.md) (migrations), [Cours 4](04-routing-controllers-validation.md) (routing/controllers/validation + couche Service), [Cours 5](05-relations-eloquent.md) (relations), [Cours 6](06-auth-permissions.md) (permissions/Policy).
>
> ⚠️ Ce doc décrit **le plan** : lis-le, valide la logique, puis on codera.

---

## 0. Où on en est

Déjà en place : l'auth (session), les 3 rôles, et les permissions **`create seances` / `update seances` / `delete seances`** (déjà seedées). Le CRUD s'appuie dessus.

Rappel des droits (le cœur de la logique) :

| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| Admin | toutes | toutes | toutes |
| Coach | les siennes | les siennes | **non** |
| Collaborateur | non | non | non |

> 💡 Point clé : « les siennes » ne se dit PAS avec une simple permission (une permission est générique : « peut modifier des séances »). Le « CETTE séance est-elle la sienne ? » se décide dans une **Policy** (§4).

---

## 1. La logique d'ensemble

Le flux d'une action, comme pour l'auth : **route → Controller (mince) → Service → Model**.

```
GET  /seances            → liste (tout le monde connecté)
GET  /seances/create     → formulaire création   (permission: create seances)
POST /seances            → enregistre             (permission: create seances + Policy)
GET  /seances/{seance}   → détail
GET  /seances/{seance}/edit → formulaire édition  (Policy: la sienne ou admin)
PUT  /seances/{seance}   → met à jour             (Policy: la sienne ou admin)
DELETE /seances/{seance} → supprime               (Policy: admin only)
```

Deux niveaux de contrôle, dans l'ordre :
1. **Permission** (middleware) : « as-tu le droit de créer/modifier des séances ? » — filtre grossier.
2. **Policy** (dans le controller) : « … mais CETTE séance-là ? » — règle fine « les siennes ».

---

## 2. Le model `Seance` + sa migration

Une commande génère model + migration + factory + seeder + controller d'un coup :

```bash
sail artisan make:model Seance -mfsc
```

La migration (champs officiels de la séance) :

```php
Schema::create('seances', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('coach_id')->constrained('users'); // le coach = un User
    $table->dateTime('started_at');
    $table->dateTime('ended_at');
    $table->unsignedInteger('max_participants')->nullable();
    $table->string('recurrence')->default('none'); // none | daily | weekly | monthly
    $table->date('recurrence_until')->nullable();
    $table->softDeletes();   // suppression douce (Cours 3)
    $table->timestamps();
});
```

> 💡 `coach_id` + `constrained('users')` = clé étrangère vers `users`. Une séance **appartient à** un coach (relation `belongsTo`, §3). Les **fichiers** (`files`) viendront à l'étape Upload, et les **participants** à l'étape Inscription — pas dans ce CRUD.

---

## 3. Le model configuré

```php
class Seance extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'coach_id', 'started_at', 'ended_at', 'max_participants', 'recurrence', 'recurrence_until'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'recurrence_until' => 'date',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
```

- `$fillable` : garde-fou d'assignation de masse (Cours 3).
- `casts()` : `started_at` devient un objet date Carbon.
- `coach()` : la relation, pour faire `$seance->coach->name` (et `with('coach')` contre le N+1, Cours 5).

---

## 4. La Policy — « le coach ne gère que les siennes »

```bash
sail artisan make:policy SeancePolicy --model=Seance
```

```php
class SeancePolicy
{
    public function create(User $user): bool
    {
        return $user->can('create seances');
    }

    public function update(User $user, Seance $seance): bool
    {
        return $user->can('update seances')
            && ($user->hasRole('admin') || $seance->coach_id === $user->id);
    }

    public function delete(User $user, Seance $seance): bool
    {
        return $user->hasRole('admin'); // seul l'admin supprime
    }
}
```

> 💡 La Policy combine la **permission** (droit générique) et la **règle métier** (`coach_id === $user->id` = « la sienne »). C'est exactement le « ses propres séances » du cahier des charges.
>
> 🔴 Rappel « en vrai chez StackTim » : en prod ce raisonnement se centralise dans un **Control + Perimeters** (lomkit access-control, Cours 6). Ici la Policy manuelle est l'étape pour **comprendre**.

---

## 5. La validation — Form Request

Une classe par écriture, qui **valide ET autorise** avant d'entrer dans le controller (Cours 4) :

```bash
sail artisan make:request StoreSeanceRequest
```

```php
class StoreSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create seances');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'coach_id' => ['required', 'exists:users,id'],
            'started_at' => ['required', 'date', 'after:now'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'recurrence' => ['required', 'in:none,daily,weekly,monthly'],
            'recurrence_until' => ['nullable', 'date', 'after:started_at'],
        ];
    }
}
```

(Une `UpdateSeanceRequest` similaire pour la modification.)

---

## 6. Le controller (mince) + la couche Service + les routes

Comme pour l'auth : le controller orchestre, un **`SeanceService`** porte la logique d'écriture.

```php
class SeanceController extends Controller
{
    public function __construct(private SeanceService $seances) {}

    public function index()
    {
        return view('seances.index', [
            'seances' => Seance::with('coach')->latest()->paginate(15), // with() = anti N+1
        ]);
    }

    public function store(StoreSeanceRequest $request)
    {
        $seance = $this->seances->create($request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance créée.']);
    }

    public function update(UpdateSeanceRequest $request, Seance $seance)
    {
        $this->authorize('update', $seance); // déclenche la Policy §4
        $this->seances->update($seance, $request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance modifiée.']);
    }

    public function destroy(Seance $seance)
    {
        $this->authorize('delete', $seance);
        $this->seances->delete($seance);

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance supprimée.']);
    }
}
```

Les routes, derrière `auth` + les permissions (Cours 4/6) :

```php
Route::middleware('auth')->group(function () {
    Route::resource('seances', SeanceController::class);
});
```

> 💡 Chaque réponse porte un **type + message** (convention notif, Cours 4). `Route::resource` génère les 7 routes CRUD d'un coup.

---

## 7. Les vues Blade

Sur la charte sombre/rouge (comme l'auth) :
- `seances/index.blade.php` — la liste (nom, coach, date, places), boutons « Créer/Modifier/Supprimer » **affichés selon les droits** (`@can('update', $seance)`).
- `seances/create.blade.php` & `edit.blade.php` — le formulaire (name, coach, started_at, max_participants), avec `@csrf`, `old()`, `@error`.
- `seances/show.blade.php` — le détail.
- Un petit **toast** qui affiche `session('notification')`.

---

## 8. Tester

1. `make fresh` (base + rôles + 3 users).
2. Connecté en **coach** : je peux créer une séance, modifier **la mienne**, mais pas supprimer, ni modifier celle d'un autre coach.
3. Connecté en **admin** : je peux tout.
4. Connecté en **collaborateur** : aucun bouton d'écriture.
5. `make check` vert avant commit.

## Checklist

- [ ] Model `Seance` + migration (name, coach_id, started_at, max_participants, softDeletes)
- [ ] Relation `coach()` + `$fillable` + `casts()`
- [ ] `SeancePolicy` (create / update « les siennes » / delete admin)
- [ ] `StoreSeanceRequest` / `UpdateSeanceRequest` (authorize + rules)
- [ ] `SeanceService` (create / update / delete) + `SeanceController` mince
- [ ] `Route::resource('seances', ...)` derrière `auth` + permissions
- [ ] Vues Blade (index / create / edit / show) + toast `notification`
- [ ] Tests des 4 scénarios de rôles · `make check` vert

⬅️ [Sommaire des cours](README.md) · Feuille de route : [XEFI 03 — Recettes](xefi-03-recettes-projet.md)
