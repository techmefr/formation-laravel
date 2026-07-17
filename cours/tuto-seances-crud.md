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
        return [
            'name' => ['required', 'string', 'max:255'],
            'place_id' => ['required', 'exists:places,id'],
            'coach_id' => ['required', 'exists:users,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
```

Deux points importants :

- **`authorize()` appelle la Policy**, pas la permission brute : `can('create', Seance::class)` déclenche `SeancePolicy::create` (§4). C'est là que le « le coach gère les siennes » se joue (pour `UpdateSeanceRequest`, on passe l'objet : `can('update', $this->route('seance'))`).
- **`prepareForValidation()`** tourne **avant** les règles : un coach ne choisit pas son `coach_id`, on le force à lui-même. admin/manager, eux, le choisissent dans un `<select>`.

(Une `UpdateSeanceRequest` quasi identique pour la modification.)

---

## 6. Le controller (mince) + la couche Service + les routes

Le controller orchestre, un **`SeanceService`** porte la logique d'écriture. **Il n'y a pas de méthode `index`/`show`/`create`/`edit`** dans le controller : ces **pages** sont servies par **Folio** (§1 bis). Le controller ne gère que les **actions** (POST/PUT/DELETE).

```php
class SeanceController extends Controller
{
    public function __construct(private SeanceService $seances) {}

    public function store(StoreSeanceRequest $request): RedirectResponse
    {
        $this->seances->create($request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance créée.']);
    }

    public function update(UpdateSeanceRequest $request, Seance $seance): RedirectResponse
    {
        $this->seances->update($seance, $request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance modifiée.']);
    }

    public function cancel(Seance $seance): RedirectResponse
    {
        $this->authorize('cancel', $seance);
        $this->seances->cancel($seance);

        return back()->with('notification', ['type' => 'success', 'message' => 'Séance annulée.']);
    }

    public function destroy(Seance $seance): RedirectResponse
    {
        $this->authorize('delete', $seance);
        $this->seances->delete($seance);

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance supprimée.']);
    }
}
```

Le Service :

```php
class SeanceService
{
    public function create(array $data): Seance { return Seance::create($data); }

    public function update(Seance $seance, array $data): void { $seance->update($data); }

    public function cancel(Seance $seance): void
    {
        $seance->cancelled_at = now();
        $seance->save();
    }

    public function delete(Seance $seance): void { $seance->delete(); } // soft delete
}
```

> ⚠️ **Piège vécu :** annuler = poser `cancelled_at`. Un `$seance->update(['cancelled_at' => now()])` **ne marche pas** si `cancelled_at` n'est pas dans `$fillable` : le mass-assignment l'ignore **en silence** (pas d'erreur, la colonne reste nulle). On l'affecte donc **en direct** (`$seance->cancelled_at = now(); $seance->save();`), ce qui contourne `$fillable`. Annuler ≠ supprimer : `cancel` pose une date, `delete` fait un **soft delete** (la séance disparaît des listes mais reste en base).

Les routes derrière `auth` — **seulement les actions**, car les pages GET sont des routes Folio (donc pas de `Route::resource`, qui écraserait `index`/`show`) :

```php
Route::middleware('auth')->group(function () {
    Route::post('/seances', [SeanceController::class, 'store'])->name('seances.store');
    Route::put('/seances/{seance}', [SeanceController::class, 'update'])->name('seances.update');
    Route::delete('/seances/{seance}', [SeanceController::class, 'destroy'])->name('seances.destroy');
    Route::post('/seances/{seance}/cancel', [SeanceController::class, 'cancel'])->name('seances.cancel');
});
```

> 💡 Chaque réponse porte un **type + message** (convention notif, Cours 4). Répartition claire : **Folio** affiche (calendrier, formulaires, détail), **le controller** exécute les écritures, **le Service** porte la logique.

---

## 7. Les vues (pages Folio)

- **`pages/seances/index.blade.php`** — la page d'accueil : le **calendrier** (FullCalendar, §10), avec le bouton **« + Nouvelle séance »** affiché selon les droits (`@can('create', App\Models\Seance::class)`).
- **`pages/seances/create.blade.php`** — le formulaire de création (name, lieu, coach si staff, début/fin, places), avec `@csrf`, `old()`, `@error`. En tête de page : `abort(403)` si l'utilisateur `cannot('create', Seance::class)`.
- **`pages/seances/[Seance]/edit.blade.php`** — le même formulaire pré-rempli, avec `@method('PUT')`, protégé par `cannot('update', $seance)`.
- **`pages/seances/[Seance].blade.php`** — le détail : infos, s'inscrire/se désinscrire, liste des participants, et les actions **Modifier / Annuler / Supprimer** affichées selon la Policy (`@can('update', $seance)`, `@can('cancel', …)`, `@can('delete', …)`).
- Un **toast** dans le layout qui affiche `session('notification')`.

> 💡 Les boutons d'écriture sont **masqués** selon les droits (`@can`), mais ce n'est que du confort d'UI : la vraie barrière reste la **Policy** appelée dans le Form Request / le controller. On ne se fie jamais au seul masquage côté vue.

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

## 10. Le calendrier (FullCalendar) + le filtrage par agence

La page d'accueil `/seances` est une **page Folio** qui affiche un vrai calendrier (**FullCalendar**), avec 3 vues : Semaine / Mois / Liste.

**Le principe** : FullCalendar est une lib **JavaScript**. On ne lui donne pas les séances en dur — il **appelle une URL** qui renvoie du **JSON**, et il dessine les événements. Comme un front Nuxt qui `fetch()` une API.

1. **La lib est bundlée par Vite** (pas de CDN) : `npm install @fullcalendar/core @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/list @fullcalendar/interaction`, on l'importe dans `resources/js/calendar.js`, et on ajoute cette entrée à `vite.config.js`.
2. **Le flux d'événements** : une route `GET /calendar/events` → `CalendarController@events` qui renvoie un tableau JSON `[{id, title, start, end, url, color}]`.
3. **La page** contient juste `<div id="calendar" data-events-url="{{ route('calendar.events') }}">`, et `calendar.js` lit cet attribut, fetch l'URL, et rend le calendrier. Clic sur un événement → page détail de la séance.

**Le rattachement à une agence** : on ajoute `agency_id` sur `users` (migration + `User::agency()`). Un lieu (`Place`) a un `type` : `agency` ou `external`. Le filtrage dans le controller :

```php
if ($isCoach) {
    $query->where('coach_id', $user->id);        // un coach ne voit QUE ses cours
} else {
    $agency = $request->query('agency', (string) ($user->agency_id ?? 'all'));
    if ($agency !== 'all') {
        $query->where(function ($q) use ($agency) {
            $q->whereHas('place', fn ($p) => $p->where('type', 'external'))
                ->orWhere('place_id', $agency);   // externe + l'agence choisie
        });
    }
    if ($request->boolean('mine')) {
        $query->whereHas('participants', fn ($q) => $q->whereKey($user->id));
    }
}
```

Le **select d'agence** (défaut = sa propre agence) et la case **« mes inscriptions »** ajoutent `?agency=…&mine=1` à l'URL ; côté JS, on appelle `calendar.refetchEvents()` au changement.

> 💡 Découpage clair : les **pages Folio** affichent, le **controller `events`** sert les données (comme une mini-API interne), le **Service** porte la logique d'écriture. Chacun son rôle.

## 11. La règle « une seule séance à la fois »

Un utilisateur ne peut pas être **inscrit** à deux séances qui se **chevauchent** dans le temps. Cette règle vit dans le **Service** (jamais dans le controller ni la vue), au moment de `register()` :

```php
public function register(Seance $seance, User $user): string
{
    if ($seance->participants()->whereKey($user->id)->exists()) {
        return 'already';
    }

    if ($this->hasTimeConflict($seance, (int) $user->id)) {
        return 'conflict';
    }

    $status = $seance->isFull() ? 'waitlist' : 'registered';
    // ... attach ...
    return $status;
}
```

`hasTimeConflict` cherche une séance où l'utilisateur est déjà **inscrit** (pas en file), non annulée, dont l'intervalle chevauche : `début_existante < fin_nouvelle` **ET** `fin_existante > début_nouvelle`.

`register()` renvoie un **résultat** (`registered` / `waitlist` / `conflict` / `already`) et le controller choisit le message de notification avec un `match`. Bonus : la promotion de la file d'attente **saute** un candidat qui serait en conflit, pour ne pas le double-booker.

> 💡 Retenir le placement : une **règle métier** (« pas deux séances à la fois ») se met dans le Service, testable et réutilisée par toutes les portes d'entrée (inscription soi-même **et** inscription par un staff).

## 12. Accessibilité (RGAA) — les réflexes

Le design system XEFI s'appuie sur le **RGAA**. Quatre réflexes appliqués ici :

1. **L'info jamais portée par la couleur seule** (règle clé). Le statut d'une séance (disponible / complet / inscrit / liste d'attente / annulée) était codé **uniquement par la couleur** de l'événement → un daltonien ne les distingue pas. On ajoute donc un **libellé texte** dans le titre — `(inscrit)`, `(complet)`, `(liste d'attente)`, `(annulée)` — plus une **légende**. (On a d'abord essayé un symbole comme `✓`/`○`, mais en vue Liste FullCalendar affiche déjà sa pastille de couleur : symbole **+** pastille = double icône. Le libellé texte est plus clair et se lit par un lecteur d'écran.)
2. **Contraste** ≥ 4.5:1 pour le texte normal (on évite les gris trop clairs sur fond sombre).
3. **Focus visible** : un `:focus-visible { outline }` global pour la navigation clavier.
4. **Labels** : chaque `<select>`/checkbox a un `<label>` ; les boutons ont un texte (pas d'icône nue sans nom accessible).

Plus : **responsive** (le header et les filtres passent en `flex-wrap`, la grille détail passe en une colonne sur mobile) et `prefers-reduced-motion` respecté.

> ⚠️ Ça couvre les points **visuellement vérifiables**. Un vrai audit RGAA complet (ordre de tabulation réel, ARIA, alternatives textuelles…) va au-delà.

---

## Checklist

- [ ] Model `Seance` + migration (name, coach_id, started_at, max_participants, softDeletes)
- [ ] Relation `coach()` + `$fillable` + `casts()`
- [ ] `SeancePolicy` (create / update « les siennes » / delete admin)
- [ ] `StoreSeanceRequest` / `UpdateSeanceRequest` (authorize + rules)
- [ ] `SeanceService` (create / update / **cancel** / delete) + `SeanceController` (actions store/update/cancel/destroy)
- [ ] Routes d'action derrière `auth` (store POST, update PUT, destroy DELETE, cancel POST) + formulaires **pages Folio** create/edit (**pas** `Route::resource` : Folio possède index/show/create/edit)
- [ ] Pivot `seance_user` (status + position) + relation `participants()` avec `withPivot`
- [ ] `InscriptionService` (register / unregister / promoteFirstWaitlisted)
- [ ] `InscriptionController` (soi-même) + `ParticipantController` (autrui, Policy `manageParticipants`)
- [ ] Règle « une séance à la fois » (conflit horaire) dans `InscriptionService`
- [ ] `agency_id` sur `users` + `User::agency()` + filtrage externe / agence
- [ ] Calendrier FullCalendar (page Folio + flux `CalendarController@events` + `calendar.js` bundlé Vite)
- [ ] Coach = ne voit que ses cours · couleur + **libellé** de statut (RGAA : jamais la couleur seule)
- [ ] Pages Folio (calendrier / create / edit / détail) + toast `notification` · focus visible · responsive
- [ ] Tests des 4 scénarios de rôles · `make check` vert

⬅️ [Sommaire des cours](README.md) · Feuille de route : [XEFI 03 — Recettes](xefi-03-recettes-projet.md)
