# XEFI 03 — Recettes du projet « Séances de sport »

> Chaque fonctionnalité du cahier des charges, traduite en étapes concrètes qui respectent les [conventions XEFI](xefi-01-conventions.md) et utilisent les [packages imposés](xefi-02-packages.md). C'est ta feuille de route de dev.

Contexte : XEFI Santé Sport veut une appli d'inscription aux séances de sport (yoga, crossfit…) pour les collaborateurs. Rôles : `admin`, `coach`, `collaborator`.

---

## Recette 0 — Le model Seance

**Champs** : `name` (string, requis), `coach` (relation user, requis), `started_at` (datetime, requis), `max_participants` (int, optionnel), `files` (media), + `slug`.

```bash
sail artisan make:model Seance -mfsc   # model + migration + factory + seeder + controller
```

```php
// migration
Schema::create('seances', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('coach_id')->constrained('users');
    $table->dateTime('started_at');
    $table->unsignedInteger('max_participants')->nullable();
    $table->softDeletes();
    $table->timestamps();
});

// table pivot pour les inscriptions
Schema::create('seance_user', function (Blueprint $table) {
    $table->foreignId('seance_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->primary(['seance_id', 'user_id']);
});
```

```php
// app/Models/Seance.php — traits XEFI empilés
class Seance extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasSlug, LogsActivity, Prunable;

    protected $fillable = ['name', 'coach_id', 'started_at', 'max_participants'];
    protected function casts(): array { return ['started_at' => 'datetime']; }

    public function coach(): BelongsTo { return $this->belongsTo(User::class, 'coach_id'); }
    public function participants(): BelongsToMany {
        return $this->belongsToMany(User::class, 'seance_user')->withTimestamps();
    }

    public function getSlugOptions(): SlugOptions {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug');
    }
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logOnly(['name', 'started_at', 'max_participants']);
    }
    public function registerMediaCollections(): void {
        $this->addMediaCollection('files')->useDisk('s3');
    }
    public function prunable() {  // convention : SoftDeletes ⇒ Prunable
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subMonths(6));
    }
}
```

---

## Recette 1 — Authentification & rôles

1. `spatie/laravel-permission` installé, `HasRoles` sur `User`.
2. Seeder des permissions + rôles :

```php
// database/seeders/RolePermissionSeeder.php
$perms = collect(['create seances', 'update seances', 'delete seances'])
    ->mapWithKeys(fn ($p) => [$p => Permission::firstOrCreate(['name' => $p])]);

Role::firstOrCreate(['name' => 'admin'])->givePermissionTo(Permission::all());
Role::firstOrCreate(['name' => 'coach'])->givePermissionTo(['create seances', 'update seances']);
Role::firstOrCreate(['name' => 'collaborator']);
```

3. Auth : Breeze/Fortify pour la Partie I web ; JWT (`tymon/jwt-auth`) pour la Partie II.
4. Politique mot de passe : `Password::defaults()` dans `AppServiceProvider` → `min(12)->mixedCase()->numbers()->symbols()`, réutilisée dans chaque validation.
5. Controllers minces → une **couche Service** (`AuthService`) porte la logique (comme un service côté front).

> 👉 Version **à la main**, pas à pas : [Cours 11 — L'authentification à la main](11-auth-a-la-main.md).

> 🔴 **En vrai chez StackTim** (`platform-api`) : l'auth de prod est **JWT en cookie via Azure OAuth**, et l'autorisation passe par **`lomkit/laravel-access-control`** (Controls + Perimeters, permissions `{method}_{scope}_{resource}`, Policies qui délèguent) — voir [Cours 6](06-auth-permissions.md) et [Cours 8](08-api-rest-jwt-lomkit.md). La version web/session est l'**étape d'apprentissage** ; la Partie II bascule vers cette cible.

---

## Recette 2 — CRUD séances avec permissions

**Policy** pour « le coach ne gère que les siennes » ([Cours 6](06-auth-permissions.md)) :

```php
// app/Policies/SeancePolicy.php
public function update(User $u, Seance $s): bool {
    return $u->can('update seances') && ($u->hasRole('admin') || $s->coach_id === $u->id);
}
public function delete(User $u, Seance $s): bool {
    return $u->hasRole('admin');   // seul l'admin supprime
}
```

**Form Request** (validation + autorisation) :

```php
// StoreSeanceRequest
public function authorize(): bool { return $this->user()->can('create seances'); }
public function rules(): array {
    return [
        'name'             => ['required', 'string', 'max:255'],
        'coach_id'         => ['required', 'exists:users,id'],
        'started_at'       => ['required', 'date', 'after:now'],
        'max_participants' => ['nullable', 'integer', 'min:1'],
    ];
}
```

**Controller mince** :

```php
public function store(StoreSeanceRequest $r): Seance { return Seance::create($r->validated()); }
public function index() { return Seance::with('coach')->withCount('participants')->latest()->paginate(15); }
public function show(Seance $seance) { return $seance->load('coach', 'participants', 'media'); }
public function destroy(Seance $seance) { $this->authorize('delete', $seance); $seance->delete(); return response()->noContent(); }
```

---

## Recette 3 — Upload de fichiers (MinIO)

Sur `store`/`update`, après création :

```php
if ($request->hasFile('files')) {
    foreach ($request->file('files') as $file) {
        $seance->addMedia($file)->toMediaCollection('files');   // → disque s3 (MinIO)
    }
}
```

Vérifier dans MinIO (`http://localhost:8900`) que les fichiers arrivent bien dans le bucket.

---

## Recette 4 — Inscription / désinscription

Le bouton « S'inscrire » est masqué si la limite est atteinte. Un endpoint dédié :

```php
// InscriptionController
public function store(Seance $seance) {
    abort_if(
        $seance->max_participants && $seance->participants()->count() >= $seance->max_participants,
        409, 'Séance complète.'
    );
    $seance->participants()->attach(auth()->id());
    return response()->noContent();
}
public function destroy(Seance $seance) {
    $seance->participants()->detach(auth()->id());
    return response()->noContent();
}
```

```php
// exposé au front / à la Resource
$complet = $seance->max_participants
    && $seance->participants()->count() >= $seance->max_participants;
```

---

## Recette 5 — Notifications mail (Mailpit) via Listener

Respecte la règle d'or : **event Eloquent → Listener → Notification**.

```php
// EventServiceProvider
protected $listen = [
    'eloquent.created: ' . Seance::class => [NotifySeanceCreated::class],
    'eloquent.deleted: ' . Seance::class => [NotifySeanceDeleted::class],
];
```

```php
// Listener
class NotifySeanceCreated {
    public function handle(Seance $seance): void {
        $dest = User::role(['admin', 'coach'])->get();
        Notification::send($dest, new SeanceCreatedNotification($seance));
    }
}
```

```php
// Notification (ShouldQueue)
class SeanceCreatedNotification extends Notification implements ShouldQueue {
    use Queueable;
    public function __construct(public Seance $seance) {}
    public function via($n): array { return ['mail']; }
    public function toMail($n): MailMessage {
        return (new MailMessage)
            ->subject('Nouvelle séance : ' . $this->seance->name)
            ->line('Une séance vient d\'être programmée.');
    }
}
```

Lancer le worker : `sail artisan queue:work`. Vérifier les mails sur `http://localhost:8025` (Mailpit).

---

## Recette 6 — Seeding réaliste (xefi/faker-php)

```php
// DatabaseSeeder
$this->call(RolePermissionSeeder::class);

$coachs = User::factory(3)->create()->each->assignRole('coach');
User::factory(10)->create()->each->assignRole('collaborator');

Seance::factory(15)
    ->recycle($coachs)                       // coachs existants
    ->create()
    ->each(fn ($s) => $s->participants()->attach(
        User::role('collaborator')->inRandomOrder()->take(rand(0, 5))->pluck('id')
    ));
```

Objectif : `sail artisan migrate:fresh --seed` → appli démontrable de bout en bout.

---

## Recette 7 — Partie II : API REST JWT + lomkit

1. `tymon/jwt-auth` : `jwt:secret`, guard `api`, `User implements JWTSubject`, `AuthController@login`.
2. Routes `routes/api.php` derrière `auth:api`.
3. `lomkit/laravel-rest-api` : `SeanceResource` (fields, filters, relations) + permissions branchées.
4. Endpoints `search` / `mutate` ; garder les Policies.

---

## Definition of done (avant validation référent)

- [ ] `migrate:fresh --seed` monte une appli complète et démontrable
- [ ] Permissions par rôle respectées (admin/coach/collaborator) + Policy « ses séances »
- [ ] Upload fichiers OK sur MinIO
- [ ] Inscription/désinscription + blocage si complet
- [ ] Mails création/suppression visibles dans Mailpit, déclenchés par Listener (pas d'Observer)
- [ ] Partie II : login JWT + endpoints lomkit sécurisés
- [ ] **Larastan ≥ 5** vert, **Pint** passé, config Sail versionnée

⬅️ [Sommaire des cours](README.md)
