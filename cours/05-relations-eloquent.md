# Cours 5 — Relations Eloquent & le piège N+1

> Les liens entre tables deviennent des méthodes PHP. Pour ton projet : une séance appartient à un coach et rassemble des participants.

## 1. Les trois relations à connaître

| Relation | Sens | Exemple projet |
|---|---|---|
| `belongsTo` | appartient à un | une séance → son coach |
| `hasMany` | possède plusieurs | un coach → ses séances |
| `belongsToMany` | plusieurs ↔ plusieurs (table pivot) | séances ↔ participants |

Une relation = une **méthode** sur le model :

```php
// app/Models/Seance.php
public function coach(): BelongsTo
{
    return $this->belongsTo(User::class, 'coach_id');  // clé étrangère coach_id
}

public function participants(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'seance_user')->withTimestamps();
}
```

```php
// app/Models/User.php  (côté inverse)
public function seances(): HasMany
{
    return $this->hasMany(Seance::class, 'coach_id');
}
```

## 2. Accéder aux relations : méthode vs propriété

Nuance importante :

```php
$seance->coach;    // PROPRIÉTÉ → exécute la requête et renvoie le model User (résultat)
$seance->coach();  // MÉTHODE   → renvoie le query builder (pour continuer à filtrer)

$seance->participants;              // Collection de User (résultat)
$seance->participants()->count();   // requête COUNT (via la méthode)
```

## 3. Many-to-many : inscriptions (attach / detach)

Le cœur de la fonctionnalité « inscription » de ton projet :

```php
// S'inscrire / se désinscrire
$seance->participants()->attach($user->id);   // INSERT dans la table pivot
$seance->participants()->detach($user->id);   // DELETE de la table pivot
$seance->participants()->toggle($user->id);   // inscrit si absent, désinscrit sinon

// Le bouton "S'inscrire" est masqué si la limite est atteinte :
$complet = $seance->participants()->count() >= $seance->max_participants;
```

La **table pivot** (`seance_user`) est créée par une migration ; convention de nommage : les deux tables au singulier, ordre alphabétique.

## 4. Le piège N+1 — LE point de revue

Boucler sur des séances puis lire `$seance->coach` déclenche **une requête par séance** (1 pour la liste + N pour les coachs) :

```php
// ❌ MAUVAIS : 1 + N requêtes
$seances = Seance::all();
foreach ($seances as $seance) {
    echo $seance->coach->name;   // une requête à chaque tour !
}
```

Solution : **eager loading** avec `with()` — Laravel charge tout en 2 requêtes :

```php
// ✅ BON : 2 requêtes au total
$seances = Seance::with('coach')->get();
foreach ($seances as $seance) {
    echo $seance->coach->name;   // déjà chargé, zéro requête
}
```

> 🔴 **Convention XEFI** « pas de requêtes dans une boucle » vise exactement ça. Charge les relations d'avance avec `with()`. Tu peux imbriquer : `with('coach', 'participants')` ou `with('participants.profile')`.

## 5. Seeders & factories — remplir la base

- Une **factory** décrit à quoi ressemble une donnée fictive.
- Un **seeder** l'utilise pour peupler la base.

```php
// database/factories/SeanceFactory.php
public function definition(): array
{
    return [
        'name'        => fake()->words(2, true),
        'started_at'  => now()->addDays(rand(1, 30)),
        'coach_id'    => User::factory(),
    ];
}
```

```php
// Créer 20 séances, chacune avec 5 participants
Seance::factory(20)->hasParticipants(5)->create();
```

```bash
sail artisan migrate:fresh --seed   # recrée la base et lance les seeders
```

> 🔴 **Convention XEFI** : on n'utilise **pas** `fakerphp/faker` mais `xefi/faker-php`, imposé pour des données réalistes.

---

## À retenir

- `belongsTo` / `hasMany` / `belongsToMany`, une relation = une méthode sur le model.
- Propriété (`->coach`) = résultat ; méthode (`->coach()`) = query builder.
- Inscriptions = `attach` / `detach` / `toggle` sur la relation many-to-many.
- **N+1** : toujours `with()` avant de boucler (convention XEFI notée en revue).
- Factories + seeders pour une base de test réaliste (avec `xefi/faker-php`).

➡️ Suite : [Cours 6 — Authentification & permissions](06-auth-permissions.md)
