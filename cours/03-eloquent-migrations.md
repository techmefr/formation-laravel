# Cours 3 — Eloquent & les migrations

> Eloquent est l'**ORM** de Laravel. Si tu connais Prisma ou Drizzle, tu as déjà 70 % du concept — mais la philosophie est *Active Record* (le model EST la ligne), pas *Data Mapper*.

## 1. Le principe : un Model = une table

```php
// app/Models/Seance.php
class Seance extends Model {}
```

Cette classe quasi vide suffit : par **convention**, le model `Seance` parle à la table `seances` (pluriel, snake_case). Tu manipules des objets PHP, Eloquent écrit le SQL.

```php
$seance = Seance::find(1);          // SELECT * FROM seances WHERE id = 1
$seance->name = 'Yoga du matin';
$seance->save();                    // UPDATE
Seance::where('coach_id', 3)->get(); // SELECT ... WHERE coach_id = 3
```

| Prisma | Eloquent |
|---|---|
| `prisma.seance.findUnique({where:{id}})` | `Seance::find($id)` |
| `prisma.seance.findMany({where:{...}})` | `Seance::where(...)->get()` |
| `prisma.seance.create({data})` | `Seance::create($data)` |
| `schema.prisma` | migrations (ci-dessous) |

**Différence clé** : en Prisma le schéma est déclaratif dans un fichier. En Laravel, le schéma se construit via des **migrations** (des étapes versionnées), et le model ne décrit PAS les colonnes — il les découvre à l'exécution.

## 2. Les migrations : ton schéma versionné

Une migration décrit une transformation de la base en PHP. Toute l'équipe applique les mêmes → même schéma, versionné dans Git.

```php
// database/migrations/…_create_seances_table.php
Schema::create('seances', function (Blueprint $table) {
    $table->id();                                        // clé primaire auto
    $table->string('name');
    $table->foreignId('coach_id')->constrained('users'); // FK vers users.id
    $table->dateTime('started_at');
    $table->unsignedInteger('max_participants')->nullable();
    $table->softDeletes();   // colonne deleted_at (voir plus bas)
    $table->timestamps();    // created_at + updated_at (gérées auto)
});
```

```bash
sail artisan make:migration create_seances_table   # créer
sail artisan migrate                                # appliquer
sail artisan migrate:fresh --seed                   # tout recréer + seeder (dev)
```

> 💡 `timestamps()` : Eloquent remplit `created_at`/`updated_at` tout seul à chaque save. Ne les gère jamais à la main.

## 3. Le model, configuré

```php
// app/Models/Seance.php
class Seance extends Model
{
    use SoftDeletes;

    // Colonnes autorisées en assignation de masse (create/update avec un array)
    protected $fillable = ['name', 'coach_id', 'started_at', 'max_participants'];

    // Conversions automatiques de type
    protected function casts(): array
    {
        return ['started_at' => 'datetime'];  // string DB → objet date Carbon
    }
}
```

- **`$fillable`** — garde-fou de sécurité. `Seance::create($request->all())` n'écrira QUE ces colonnes (empêche qu'un client injecte `is_admin` par ex.). Sans ça → erreur `MassAssignmentException`.
- **`casts()`** — `started_at` devient un objet date **Carbon** (manipulation de dates fluide) au lieu d'une string.

## 4. Créer / lire / mettre à jour

```php
// Créer
Seance::create(['name' => 'Yoga', 'coach_id' => 3, 'started_at' => now()]);

// firstOrCreate : cherche, sinon crée (parfait pour le seeding, pas de doublon)
User::firstOrCreate(
    ['email' => 'coach@xefi.fr'],  // critère de recherche
    ['name'  => 'Coach Yoga']      // valeurs si création
);

// updateOrCreate : met à jour si trouvé, sinon crée
// firstOrNew : comme firstOrCreate mais NE sauvegarde pas (à toi de ->save())
```

## 5. Soft deletes — supprimer sans effacer

Un **soft delete** remplit la colonne `deleted_at` au lieu d'effacer la ligne. Elle est ignorée par défaut mais reste récupérable et auditable.

```php
$seance->delete();        // remplit deleted_at (la ligne existe encore)
```

| Requête | Effet |
|---|---|
| `Seance::all()` | exclut les supprimés (défaut) |
| `Seance::withTrashed()->get()` | **inclut** les soft-deleted |
| `Seance::onlyTrashed()->get()` | uniquement les supprimés |
| `$seance->restore()` | ressuscite |

> 🔴 **Convention XEFI** : un model en `SoftDeletes` doit aussi être `Prunable` — prévoir un nettoyage périodique des lignes vraiment obsolètes, sinon la table gonfle indéfiniment.

## 6. Tinker — ton REPL

Pour expérimenter Eloquent en direct, sans écrire de route :

```bash
sail artisan tinker
>>> Seance::count()
>>> Seance::create(['name' => 'Test', 'coach_id' => 1, 'started_at' => now()])
```

C'est ton `node` interactif, branché sur ta vraie base.

---

## À retenir

- Model = table (convention pluriel). Le model ne décrit pas les colonnes ; les **migrations** construisent le schéma, versionné.
- `$fillable` protège l'assignation de masse ; `casts()` convertit les types (dates → Carbon).
- `firstOrCreate` / `updateOrCreate` pour un seeding idempotent.
- Soft delete = `deleted_at` ; `withTrashed()` pour les revoir. XEFI → aussi `Prunable`.
- `tinker` pour tester à la main.

➡️ Suite : [Cours 4 — Routing, Controllers & validation](04-routing-controllers-validation.md)
