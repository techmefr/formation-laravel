# Tuto — Le CRUD des séances

> But : comprendre **la logique** du CRUD séances (créer / lister / voir / modifier / supprimer) avec les **permissions par rôle**, avant de le construire. Toujours en **session + Blade** (comme l'auth). On réutilise : [Cours 3](03-eloquent-migrations.md) (migrations), [Cours 4](04-routing-controllers-validation.md) (routing/controllers/validation + couche Service), [Cours 5](05-relations-eloquent.md) (relations), [Cours 6](06-auth-permissions.md) (permissions/Policy).
>
> ⚠️ Ce doc décrit **le plan** : lis-le, valide la logique, puis on codera.

---

## 0. Où on en est

Déjà en place : l'auth (session) et **4 rôles** (`admin`, `manager`, `coach`, `collaborator`) avec leurs permissions (`create` / `update` / `cancel` / `delete seances`, `manage participants`) — déjà seedées. Le CRUD s'appuie dessus.

Rappel des droits (le cœur de la logique) :

| Rôle | Créer | Modifier | Annuler | Supprimer |
|---|---|---|---|---|
| Admin | toutes | toutes | toutes | toutes |
| Manager | — | toutes | toutes | toutes |
| Coach | les siennes | les siennes | les siennes | les siennes |
| Collaborateur | — | — | — | — |

**S'inscrire / se désinscrire** = tout utilisateur connecté, pour lui-même → c'est une action **séparée** (pas le droit `update` de la séance).

> 💡 Point clé : « les siennes » ne se dit PAS avec une simple permission (générique : « peut modifier des séances »). Le « CETTE séance est-elle la sienne ? » se décide dans une **Policy** (§4).

---

## Ordre de construction d'une feature

Toujours dans cet ordre — chaque étape s'appuie sur la précédente :

1. **Model + migration** — l'entité et sa table (`make:model X -m`).
2. **Factory** — le moule d'une fausse donnée (a besoin du model).
3. **Permissions & rôles** (seeder) — les droits atomiques (`create/update…`).
4. **Seeder** — remplir la base (a besoin du model + factory + permissions).
5. **Policy** — les règles d'autorisation par objet (a besoin du model + permissions).
6. **CRUD** — Form Requests + Controller + routes + vues (a besoin de tout ce qui précède).

> 💡 Pourquoi cet ordre : on ne peut pas seeder sans model/factory, ni écrire une Policy qui teste des permissions inexistantes, ni brancher un controller sans Policy ni validation. On construit **des fondations vers la surface**.

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

**Qu'est-ce qu'une Policy ?** Une classe qui répond à « **CET utilisateur a-t-il le droit de faire CETTE action sur CET objet ?** ». Une **permission** dit « peut modifier des séances » (générique) ; la **Policy** ajoute la règle **par objet** (« … mais cette séance-ci est-elle la sienne ? »). Une méthode = une action (`create`, `update`, `cancel`, `delete`…) qui renvoie `true`/`false`. Laravel l'appelle via `$this->authorize('update', $seance)` (→ 403 si refusé) ou `@can('update', $seance)` dans une vue, et relie tout seul `Seance` → `SeancePolicy` (convention de nommage).

> 💡 Analogie : la **permission** = ton badge (« tu peux entrer dans les salles de réunion ») ; la **Policy** = le videur qui vérifie en plus que **cette** salle-ci est bien la tienne.

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

## 9. L'inscription & la file d'attente

**Le piège à éviter :** s'inscrire, c'est écrire dans la table pivot `seance_user`… donc on serait tenté d'appeler `update` sur la séance. **Non.** Modifier une séance (titre, type) et s'y inscrire sont **deux actions différentes** : un collaborateur doit pouvoir s'inscrire *sans* avoir le droit de modifier la séance. On sépare donc les routes, les controllers et les autorisations.

### Le pivot

`seance_user` n'est pas un simple lien : il porte deux infos par inscription.

```php
$table->foreignId('seance_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('status');        // 'registered' ou 'waitlist'
$table->unsignedInteger('position');
$table->timestamps();
$table->primary(['seance_id', 'user_id']);
```

Côté model, la relation expose ces colonnes avec `withPivot` (comme un `include` Prisma qui ramènerait les champs de la table de jointure) :

```php
public function participants(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('status', 'position')
        ->withTimestamps();
}
```

### La logique dans un Service

Toute la mécanique file d'attente vit dans `InscriptionService` — pas dans le controller.

```php
public function register(Seance $seance, User $user): void
{
    if ($seance->participants()->whereKey($user->id)->exists()) {
        return; // déjà inscrit, on ne fait rien
    }

    $status = $seance->isFull() ? 'waitlist' : 'registered';
    $position = $seance->participants()->count() + 1;

    $seance->participants()->attach($user->id, [
        'status' => $status,
        'position' => $position,
    ]);
}

public function unregister(Seance $seance, User $user): void
{
    $wasRegistered = $seance->participants()
        ->whereKey($user->id)
        ->wherePivot('status', 'registered')
        ->exists();

    $seance->participants()->detach($user->id);

    if ($wasRegistered) {
        $this->promoteFirstWaitlisted($seance);
    }
}
```

- `isFull()` compte les `registered` et compare à `max_participants` (`null` = illimité).
- Si c'est plein → on inscrit en `waitlist`.
- Quand un `registered` se désinscrit → `promoteFirstWaitlisted` prend le **premier de la file** (`orderByPivot('position')`) et le passe en `registered` via `updateExistingPivot`.

### Deux controllers, deux autorisations

| Qui | Route | Controller | Autorisation |
|-----|-------|------------|--------------|
| N'importe quel connecté, **pour lui-même** | `POST/DELETE /seances/{seance}/inscription` | `InscriptionController` | aucune (l'`auth` suffit) |
| admin / manager / coach (sa séance), **pour autrui** | `POST /seances/{seance}/participants`, `DELETE .../participants/{user}` | `ParticipantController` | `$this->authorize('manageParticipants', $seance)` |

```php
Route::middleware('auth')->group(function () {
    Route::post('/seances/{seance}/inscription', [InscriptionController::class, 'store'])->name('seances.inscription.store');
    Route::delete('/seances/{seance}/inscription', [InscriptionController::class, 'destroy'])->name('seances.inscription.destroy');

    Route::post('/seances/{seance}/participants', [ParticipantController::class, 'store'])->name('seances.participants.store');
    Route::delete('/seances/{seance}/participants/{user}', [ParticipantController::class, 'destroy'])->name('seances.participants.destroy');
});
```

`InscriptionController` lit `auth()->user()` — l'utilisateur ne peut agir que sur lui-même, aucune Policy nécessaire. `ParticipantController` reçoit un `user_id` et passe par la Policy `manageParticipants` (même règle « le coach ne gère que ses séances » que §4).

---

## Checklist

- [ ] Model `Seance` + migration (name, coach_id, started_at, max_participants, softDeletes)
- [ ] Relation `coach()` + `$fillable` + `casts()`
- [ ] `SeancePolicy` (create / update « les siennes » / delete admin)
- [ ] `StoreSeanceRequest` / `UpdateSeanceRequest` (authorize + rules)
- [ ] `SeanceService` (create / update / delete) + `SeanceController` mince
- [ ] `Route::resource('seances', ...)` derrière `auth` + permissions
- [ ] Pivot `seance_user` (status + position) + relation `participants()` avec `withPivot`
- [ ] `InscriptionService` (register / unregister / promoteFirstWaitlisted)
- [ ] `InscriptionController` (soi-même) + `ParticipantController` (autrui, Policy `manageParticipants`)
- [ ] Vues Blade (index / create / edit / show) + toast `notification`
- [ ] Tests des 4 scénarios de rôles · `make check` vert

⬅️ [Sommaire des cours](README.md) · Feuille de route : [XEFI 03 — Recettes](xefi-03-recettes-projet.md)
