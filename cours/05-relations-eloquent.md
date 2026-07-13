# Cours 5 — Relations Eloquent & le piège N+1

> Objectif : relier tes tables entre elles **sans écrire de jointure SQL**. Tu connais déjà les relations d'ORM (Prisma, Drizzle) — ici on ne fait que traduire les réflexes. Pour ton projet : une séance appartient à un coach et rassemble des participants.

---

## 0. Le kit de survie (à lire en premier)

Trois idées suffisent à comprendre 90 % du chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **Une relation = une méthode sur le model** | Pas de `include` déclaratif comme en Prisma. Tu écris une méthode `coach()` sur le model, et Eloquent en déduit la jointure. |
| **Propriété vs méthode** | `$seance->coach` (sans parenthèses) te rend le **résultat** ; `$seance->coach()` (avec parenthèses) te rend le **query builder** pour continuer à filtrer. |
| **N+1 = boucle + relation non chargée** | Le même piège que dans tous les ORM : lire une relation dans une boucle déclenche une requête par tour. La parade est toujours `with()`. |

Le reste, ce sont des raccourcis pratiques autour de ces trois idées.

---

## 1. Les trois relations à connaître

| Relation | Sens | Exemple projet |
|---|---|---|
| `belongsTo` | appartient à un | une séance → son coach |
| `hasMany` | possède plusieurs | un coach → ses séances |
| `belongsToMany` | plusieurs ↔ plusieurs (table pivot) | séances ↔ participants |

Une relation se déclare comme une **méthode** sur le model. Le corps de la méthode dit juste « quel type de lien, vers quel autre model, via quelle clé » :

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

> 💡 En Prisma, tu décris la relation **dans le schéma** (`coach User @relation(...)`) et tu la charges à la volée avec `include: { coach: true }`. En Laravel, la relation est **une méthode** sur le model, et c'est cette même méthode qui sert à la fois à la déclarer et à la charger. Une seule notion à retenir au lieu de deux.

---

## 2. Accéder aux relations : méthode vs propriété

C'est LA nuance qui déroute au début, alors prends 30 secondes dessus.

```php
$seance->coach;    // PROPRIÉTÉ → exécute la requête et renvoie le model User (résultat)
$seance->coach();  // MÉTHODE   → renvoie le query builder (pour continuer à filtrer)

$seance->participants;              // Collection de User (résultat)
$seance->participants()->count();   // requête COUNT (via la méthode)
```

> 💡 Le réflexe : **sans parenthèses = donne-moi la donnée** ; **avec parenthèses = donne-moi la requête que je vais affiner**. Un peu comme la différence entre `await prisma.seance.findFirst(...)` (tu récupères l'objet) et le query builder que tu enchaînes avant de l'exécuter.

---

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

> 💡 La table pivot, c'est l'équivalent de la table de jointure explicite que Prisma génère (ou que tu déclares) pour un `many-to-many`. Ici tu la nommes toi-même, mais Laravel remplit et vide ses lignes pour toi via `attach` / `detach`.

---

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

> 💡 C'est exactement le N+1 que tu connais avec n'importe quel ORM. `with('coach')` est le pendant du `include: { coach: true }` de Prisma : tu déclares d'avance ce dont tu auras besoin dans la boucle, pour que tout soit chargé en un seul coup.

> 🔴 **Convention XEFI** « pas de requêtes dans une boucle » vise exactement ça. Charge les relations d'avance avec `with()`. Tu peux imbriquer : `with('coach', 'participants')` ou `with('participants.profile')`.

---

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

> 💡 Une factory, c'est un **générateur de données de test** : au lieu d'écrire 20 objets à la main, tu décris une fois le « moule » et tu en tires autant d'exemplaires que tu veux. Même esprit que les fixtures/factories que tu utilises pour tes tests JS.

> 🔴 **Convention XEFI** : on n'utilise **pas** `fakerphp/faker` mais `xefi/faker-php`, imposé pour des données réalistes.

### En pratique avec `xefi/faker-php`

Le faker maison s'instancie comme un simple objet, et tu appelles ses méthodes pour générer des données :

```php
sail composer require xefi/faker-php --dev
```

```php
$faker = new \Xefi\Faker\Faker();

$faker->name();      // "John Doe"        → noms & texte
$faker->sentence();  // "Yoga du matin…"  → phrases
$faker->iban();      // un IBAN valide
```

La factory `Seance` version XEFI (celle du dessus utilisait `fake()` juste pour illustrer) :

```php
// database/factories/SeanceFactory.php
public function definition(): array
{
    $faker = new \Xefi\Faker\Faker();   // ← le faker XEFI, pas fake()

    return [
        'name'             => $faker->sentence(),           // texte réaliste
        'coach_id'         => User::factory(),
        'started_at'       => now()->addDays(rand(1, 30)),  // une date : Carbon suffit
        'max_participants' => rand(8, 20),
    ];
}
```

> 💡 Le principe est le même que `fakerphp/faker` (une méthode = un type de donnée), donc rien de dépaysant. `xefi/faker-php` couvre aussi **dates, nombres, booléens**, plus des modificateurs **`unique()`** (pas de doublon) et **`optional()`** (valeur parfois nulle). Les noms exacts de ces providers/modificateurs sont dans la doc officielle → **faker-php.xefi.com** (à garder sous la main : c'est le package imposé, autant connaître ses méthodes).

---

## À retenir

- `belongsTo` / `hasMany` / `belongsToMany`, une relation = une méthode sur le model.
- Propriété (`->coach`) = résultat ; méthode (`->coach()`) = query builder.
- Inscriptions = `attach` / `detach` / `toggle` sur la relation many-to-many.
- **N+1** : toujours `with()` avant de boucler (convention XEFI notée en revue).
- Factories + seeders pour une base de test réaliste (avec `xefi/faker-php`).

## ⚠️ Les pièges qui piquent au début

1. **Confondre `$seance->coach` et `$seance->coach()`.** Sans parenthèses tu obtiens la donnée (le model User) ; avec parenthèses tu obtiens le query builder. Si tu tentes `$seance->coach->where(...)`, ça casse : c'est déjà un résultat, pas une requête.
2. **Le N+1.** Lire une relation dans un `foreach` sans avoir fait `with()` avant, c'est une requête par tour de boucle. Invisible sur 3 lignes en local, catastrophique sur 5 000 en prod. Charge d'avance.
3. **Nommer la table pivot au hasard.** La convention attend les deux noms au **singulier** et dans l'**ordre alphabétique** (`seance_user`, pas `user_seance` ni `seances_users`). Respecte-la et Eloquent câble le pivot tout seul.

➡️ Suite : [Cours 6 — Authentification & permissions](06-auth-permissions.md)
