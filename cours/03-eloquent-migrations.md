# Cours 3 — Eloquent & les migrations

> Objectif : savoir **lire et écrire dans la base** sans écrire une ligne de SQL. Tu connais déjà les ORM (Prisma, Drizzle) — on ne fait que traduire les réflexes, une notion à la fois.

---

## 0. Le kit de survie (à lire en premier)

Deux idées suffisent à comprendre 90 % du chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **1 Model = 1 table** | La classe `Seance` ↔ la table `seances`. Tu manipules des objets PHP, Eloquent écrit le SQL. |
| **Le schéma vit dans les migrations, pas dans le model** | Contrairement à Prisma (`schema.prisma`), le model ne liste PAS ses colonnes. Ce sont les **migrations** (fichiers versionnés dans Git) qui créent la table. |

Le reste, ce sont des raccourcis pratiques autour de ces deux idées.

---

## 1. Le principe : un Model = une table

```php
// app/Models/Seance.php
class Seance extends Model {}
```

Cette classe **quasi vide** suffit. Par **convention**, le model `Seance` parle à la table `seances` (pluriel, snake_case). Tu ne configures rien : tu nommes bien, ça se câble.

```php
$seance = Seance::find(1);            // SELECT * FROM seances WHERE id = 1
$seance->name = 'Yoga du matin';
$seance->save();                      // UPDATE
Seance::where('coach_id', 3)->get();  // SELECT ... WHERE coach_id = 3
```

Tu retrouves tes réflexes d'ORM :

| Prisma | Eloquent |
|---|---|
| `prisma.seance.findUnique({where:{id}})` | `Seance::find($id)` |
| `prisma.seance.findMany({where:{...}})` | `Seance::where(...)->get()` |
| `prisma.seance.create({data})` | `Seance::create($data)` |
| `schema.prisma` | migrations (section 2) |

> 💡 **La vraie différence avec Prisma.** En Prisma, le schéma est **déclaratif** dans un seul fichier. En Laravel, on construit la base par **étapes versionnées** (les migrations), et le model **ne décrit pas ses colonnes** — il les découvre à l'exécution. C'est le style *Active Record* (« le model EST la ligne ») au lieu de *Data Mapper*.

---

## 2. Les migrations : ton schéma versionné

Une **migration** décrit une transformation de la base, écrite en PHP. Toute l'équipe applique les mêmes migrations → tout le monde a le même schéma, tracé dans Git.

> 💡 Analogie : c'est un `git commit`, mais pour la structure de ta base. Chaque migration est une étape que Laravel sait rejouer dans l'ordre.

```php
// database/migrations/…_create_seances_table.php
Schema::create('seances', function (Blueprint $table) {
    $table->id();                                        // clé primaire auto (bigint)
    $table->string('name');
    $table->foreignId('coach_id')->constrained('users'); // clé étrangère → users.id
    $table->dateTime('started_at');
    $table->unsignedInteger('max_participants')->nullable();
    $table->softDeletes();   // ajoute la colonne deleted_at (voir section 5)
    $table->timestamps();    // ajoute created_at + updated_at (gérées auto)
});
```

Les commandes du quotidien :

```bash
sail artisan make:migration create_seances_table   # créer le fichier
sail artisan migrate                                # appliquer les migrations en attente
sail artisan migrate:fresh --seed                   # TOUT recréer + relancer les seeders (dev)
```

> 💡 `timestamps()` : Eloquent remplit `created_at` / `updated_at` **tout seul** à chaque save. Tu n'y touches jamais à la main.

> 🔴 **`migrate:fresh` DROP toutes les tables** puis rejoue tout depuis zéro. En local c'est parfait pour repartir propre. **Jamais sur une base partagée / staging / prod** : tu effaces les données de tout le monde. Un `make fresh` copié bêtement d'un projet local vers un env partagé = wipe. En dehors du local, on n'utilise que `migrate` (qui n'applique que les migrations **en attente** et ne touche pas aux données existantes). C'est aussi pour ça que `migrate` seul **ne relance pas les seeders** : seul `--seed` (via `migrate:fresh --seed` ou `db:seed`) les rejoue.

---

## 3. Le model, configuré

La classe vide marche, mais en pratique on lui ajoute deux ou trois réglages :

```php
// app/Models/Seance.php
class Seance extends Model
{
    use SoftDeletes;

    // Colonnes autorisées en assignation de masse (create/update avec un array)
    protected $fillable = ['name', 'coach_id', 'started_at', 'max_participants'];

    // Conversions de type automatiques
    protected function casts(): array
    {
        return ['started_at' => 'datetime'];  // la string en base devient un objet date Carbon
    }
}
```

- **`$fillable`** — un garde-fou de sécurité. `Seance::create($request->all())` n'écrira **que** ces colonnes. Ça empêche un client malin d'injecter un champ non prévu (`is_admin`, par ex.). Si tu oublies de lister une colonne → erreur `MassAssignmentException`.
- **`casts()`** — `started_at` te revient en objet date **Carbon** (manipulation de dates fluide : `->addDays(7)`, `->diffForHumans()`) au lieu d'une simple string.

> 💡 `$fillable`, c'est l'esprit d'un **DTO / whitelist** : tu déclares explicitement ce que le monde extérieur a le droit de remplir.

---

## 4. Créer / lire / mettre à jour

```php
// Créer
Seance::create(['name' => 'Yoga', 'coach_id' => 3, 'started_at' => now()]);

// firstOrCreate : cherche, sinon crée (parfait pour le seeding : pas de doublon)
User::firstOrCreate(
    ['email' => 'coach@xefi.fr'],  // critère de recherche
    ['name'  => 'Coach Yoga']      // valeurs utilisées seulement si création
);

// updateOrCreate : met à jour si trouvé, sinon crée
// firstOrNew   : comme firstOrCreate, mais NE sauvegarde pas (à toi de faire ->save())
```

> 💡 `firstOrCreate` / `updateOrCreate` rendent un script **idempotent** : tu peux le relancer 10 fois sans créer 10 doublons. C'est exactement ce qu'on veut pour un seeder.

---

## 5. Soft deletes — supprimer sans effacer

Un **soft delete** ne supprime pas la ligne : il remplit une colonne `deleted_at`. La ligne devient invisible par défaut, mais reste récupérable et auditable.

```php
$seance->delete();        // remplit deleted_at — la ligne existe toujours en base
```

| Requête | Effet |
|---|---|
| `Seance::all()` | exclut les supprimés (comportement par défaut) |
| `Seance::withTrashed()->get()` | **inclut** les soft-deleted |
| `Seance::onlyTrashed()->get()` | uniquement les supprimés |
| `$seance->restore()` | ressuscite la ligne |

> 🔴 **Convention XEFI** : un model en `SoftDeletes` doit **aussi** être `Prunable` — c'est-à-dire prévoir un nettoyage périodique des lignes vraiment obsolètes. Sinon la table gonfle indéfiniment avec des lignes « supprimées » jamais effacées.

---

## 6. Tinker — ton REPL

Pour expérimenter Eloquent en direct, sans écrire de route ni de controller :

```bash
sail artisan tinker
>>> Seance::count()
>>> Seance::create(['name' => 'Test', 'coach_id' => 1, 'started_at' => now()])
```

> 💡 C'est ton `node` interactif, mais branché sur ta **vraie base**. Idéal pour vérifier une requête ou l'existence d'une donnée.

### ⚠️ Tinker n'est PAS ton `npm run`

Piège classique : tinker n'est **pas** un lanceur de scripts. C'est une **console interactive** (REPL), pas `npm run <tâche>`. Le vrai équivalent de tes scripts JS, c'est trois autres choses :

| Ton monde JS | Équivalent Laravel/PHP | Exemple |
|---|---|---|
| REPL `node` | **`artisan tinker`** | `sail artisan tinker` |
| `package.json` → `"scripts"` | **`composer.json` → `"scripts"`** | `composer run dev` |
| `npm run <tâche>` maison | **commandes Artisan** (une tâche = une commande) | `sail artisan migrate`, `sail artisan queue:work` |
| Task-runner par-dessus | le **`Makefile`** du projet | `make up`, `make check`, `make fresh` |

Donc :
- **Tester un bout de code une fois, à la main** → **tinker** (jetable, exploratoire).
- **Écrire une tâche réutilisable** (comme un script npm) → une **commande Artisan** (`sail artisan make:command`), un script `composer.json`, ou une cible `Makefile`.

> 🔴 Convention du projet (CLAUDE.md) : pour **prouver** qu'un truc marche, préfère un **test avec factory** plutôt que tinker. Tinker sert à explorer, pas à valider durablement.

---

## Aide-mémoire — les colonnes de migration

**Types courants** (avec l'équivalent Prisma) :

| Migration | SQL | ≈ Prisma |
|---|---|---|
| `id()` | `bigint` auto PK | `@id @default(autoincrement())` |
| `string('name', 255)` | `VARCHAR` | `String` |
| `text('description')` | `TEXT` | `String` |
| `integer` / `unsignedInteger` | `INT` (signé / non) | `Int` |
| `boolean('active')` | `TINYINT(1)` | `Boolean` |
| `dateTime` / `date` / `timestamp` | date/heure | `DateTime` |
| `decimal('prix', 8, 2)` | `DECIMAL` | `Decimal` |
| `json('meta')` | `JSON` | `Json` |
| `enum('statut', ['a','b'])` | `ENUM` | `enum` |
| `foreignId('coach_id')` | `bigint` (FK) | relation |

**Modificateurs à chaîner** : `->nullable()`, `->default(x)`, `->unique()`, `->index()`, `->comment('…')`.

**Clés étrangères** :

```php
$table->foreignId('coach_id')->constrained('users');            // FK vers users.id
$table->foreignId('coach_id')->constrained()->cascadeOnDelete(); // + suppression en cascade
```

**Les « tout-en-un »** : `id()` (PK), `timestamps()` (created_at + updated_at), `softDeletes()` (deleted_at), `rememberToken()` (rester connecté), `morphs('x')` (relation polymorphe → 2 colonnes).

## À retenir

- **1 Model = 1 table** (convention pluriel). Le model ne décrit pas les colonnes : ce sont les **migrations** (versionnées dans Git) qui construisent le schéma.
- `$fillable` protège l'assignation de masse ; `casts()` convertit les types (string DB → date Carbon).
- `firstOrCreate` / `updateOrCreate` pour un seeding **idempotent** (relançable sans doublon).
- Soft delete = colonne `deleted_at` ; `withTrashed()` pour les revoir. Chez XEFI → aussi `Prunable`.
- `tinker` = REPL branché sur ta base, pour tester à la main.

## Questions qui reviennent

**« `timestamps()` crée aussi `deleted_at` ? »**
Non. `timestamps()` crée uniquement **`created_at` + `updated_at`** (remplies automatiquement par Eloquent à chaque `create`/`save`). `deleted_at` vient d'une méthode **séparée**, `softDeletes()`, et n'est réellement exploitée que si le model a `use SoftDeletes;`.

| Tu écris dans la migration | Colonnes créées |
|---|---|
| `$table->timestamps()` | `created_at`, `updated_at` |
| `$table->softDeletes()` | `deleted_at` |
| les deux | les trois |

> Timestamps activés par défaut ; pour les couper : `public $timestamps = false;` dans le model.

**« `$table`, c'est le nom de la table ? »**
Non. Le vrai nom de la table, c'est la **chaîne** dans `Schema::create('seances', …)`. `$table` n'est que le **nom d'un paramètre** — un objet `Blueprint` (le « constructeur de table ») que Laravel te passe. Tu pourrais l'appeler `$t`.

```php
Schema::create('users', function (Blueprint $table) {   // ← encore $table, pas $user
    $table->string('email');
});
```

C'est comme le `item` dans `.map((item) => …)` : un nom que **tu** choisis, pas une valeur magique liée à la table.

**« Pourquoi Eloquent découvre les colonnes à l'exécution ? »**
Le model **reflète la ligne** (*Active Record*) : il range les colonnes ramenées par la requête dans un tableau interne, et `$seance->name` lit ce tableau via une méthode magique PHP. Choix **DRY** : la base connaît déjà ses colonnes (via les migrations), inutile de les redéclarer.

| | Prisma / TypeORM | Eloquent |
|---|---|---|
| Colonnes déclarées | schéma **+** DB | **DB seule** (migration) |
| Autocomplétion typée | ✅ | ❌ (compensé par `laravel-ide-helper`) |

Contrepartie assumée : pas de typage statique des champs. Le seul endroit où tu **listes** des colonnes dans le model, c'est `$fillable` — pour la **sécurité**, pas pour définir ce qui existe.

**« `delete()` supprime ou fait un soft delete ? »**
Il n'existe **pas** deux méthodes : c'est toujours `->delete()`. Son comportement dépend uniquement du **trait** posé sur le model :

| Le model a… | Ce que fait `->delete()` |
|---|---|
| `use SoftDeletes;` | `UPDATE … SET deleted_at = now()` (soft) |
| rien | vrai `DELETE` SQL (la ligne disparaît) |

Pour forcer une vraie suppression **malgré** le trait : `->forceDelete()`. C'est donc le trait (+ la colonne `deleted_at`) qui pilote, pas une méthode à choisir au cas par cas.

**« Pourquoi la migration s'appelle `0001_01_01_000000_create_users_table` ? »**
C'est un **horodatage** au format `AAAA_MM_JJ_HHMMSS`. Laravel exécute les migrations dans l'**ordre alphabétique** du nom, qui correspond donc à l'ordre chronologique. `0001_01_01` est une date « au tout début des temps » posée **volontairement** par Laravel pour que ses tables de base (`users`, `cache`, `jobs`) passent **toujours en premier**, avant tes migrations à toi (datées 2025/2026). Les secondes (`000000` / `000001` / `000002`) ne servent qu'à ordonner ces 3 tables entre elles.

> 💡 Tu ne nommes **jamais** ce préfixe : `make:migration` génère l'horodatage réel tout seul. C'est exactement le préfixe horodaté des migrations **Prisma**.

**« C'est quoi `#[Fillable([...])]` au-dessus de la classe ? »**
C'est la **nouvelle syntaxe** (les *attributs* PHP 8) pour déclarer ce que faisait `protected $fillable = [...]` / `protected $hidden = [...]`. Même comportement, forme plus **déclarative** : une métadonnée posée au-dessus de la classe.

> 💡 Analogie directe : les **décorateurs** NestJS / `class-validator` / `class-transformer`. `#[Hidden(['password'])]` ≈ `@Exclude()`. Les deux syntaxes marchent.

⚠️ Le squelette **Laravel 13** de la formation utilise les attributs, mais le vrai projet StackTim (`platform-api`) utilise la forme classique `$fillable` / `$hidden`. Suis toujours la **convention du fichier** sur lequel tu travailles.

**« C'est quoi `up()` et `down()` dans une migration ? »**
Une migration est **réversible** :
- **`up()`** = ce qu'on fait quand on **applique** la migration (créer la table + colonnes). Lancé par `sail artisan migrate`.
- **`down()`** = l'**inverse**, pour **annuler** (`Schema::dropIfExists('seances')`). Lancé par `sail artisan migrate:rollback`.

Pour un `create`, `make:migration` remplit déjà le `down()` — tu ne touches qu'au `up()`.

> 💡 Analogie : le `up`/`down` des migrations Knex/TypeORM. Pense **`up` = installer / `down` = désinstaller**.

⚠️ Ne pas confondre avec `make up` / `make down` (Docker) : ceux-là **démarrent/arrêtent les conteneurs** (aucune perte de données). Le `down` d'une migration **détruit la table** → les données dedans partent avec.

**« Et les données quand je `down` puis `up` à nouveau ? »**
C'est LE piège. **Une migration gère la *structure*, jamais les *données*.** `up()` construit la forme, `down()` la défait — mais rien ne **stocke** le contenu. Donc tout ce qui vivait dans la partie annulée est **perdu**, et un `up()` ensuite recrée une structure **vide** (les données ne reviennent pas).

Exemple concret — un **feature flag** en colonne :

```php
public function up(): void {
    Schema::table('users', fn (Blueprint $table) => $table->boolean('is_beta')->default(false));
}
public function down(): void {
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_beta'));
}
```

1. `migrate` → la colonne `is_beta` existe. Tu passes 40 users à `true`.
2. `migrate:rollback` → exécute `down()` = `dropColumn('is_beta')` → **les 40 `true` (et tous les `false`) sont détruits**. Les lignes `users` restent, mais la colonne n'existe plus.
3. `migrate` → `up()` recrée `is_beta` **avec sa valeur par défaut (`false`) pour tout le monde**. Tes 40 « beta » sont repassés à `false`. **Données perdues** : la structure est revenue, pas le contenu.

Ce que `down()` détruit exactement :

| `down()` fait… | Les lignes | Données perdues |
|---|---|---|
| `dropColumn('x')` | **restent** | la colonne `x` et **toutes ses valeurs** |
| `dropIfExists('table')` (down d'un `create`) | **disparaissent** | **toute la table + ses lignes** |
| renommer / changer le type d'une colonne | restent | risque de perte/troncature selon la transfo |

**Conséquences pratiques :**
- **En local** : aucun souci → `migrate:fresh --seed` régénère des données de test. C'est fait pour ça.
- **En prod / base partagée** : on ne compte **jamais** sur `down()` pour revenir en arrière sans perte. Règle **forward-only** : si tu t'es trompé, tu écris une **nouvelle migration** qui corrige, tu ne rollback pas. Et avant tout changement risqué → **backup**.
- Pour **préserver/transformer** des données lors d'un changement de schéma (ex. splitter une colonne), tu écris le **backfill dans le `up()`** (lire l'ancienne valeur, remplir la nouvelle). Le `down()` ne pourra de toute façon pas tout reconstituer.
- Cas du **feature flag** : s'il est **en base**, un rollback détruit son état. C'est pour ça qu'on met souvent un flag en **config** (`config/features.php` piloté par `.env`) ou dans un service dédié — sa valeur ne dépend alors d'aucune migration réversible.

> 🎯 `down()` restaure la **structure**, jamais les **données**. Rollback = destructif pour ce qui vivait dans la partie annulée. Hors local : forward-only + backups.

**« Plusieurs migrations dans la même journée ? »**
Oui, c'est normal (une migration = un petit changement). L'horodatage est précis à la **seconde** (`AAAA_MM_JJ_HHMMSS`), donc deux migrations le même jour ont des noms différents et s'appliquent dans l'ordre de création. `make:migration` pose ce préfixe tout seul.

**« À quoi sert l'ordre des migrations ? »**
Certaines tables **dépendent** d'autres. Ta table `seances` a une clé étrangère `coach_id` → `users` : la table `users` doit donc **exister avant**. Laravel exécute les migrations dans l'ordre de leur horodatage (le plus ancien d'abord), donc si tu les crées dans l'ordre logique, ça tombe juste.

> Règle simple : **crée d'abord la table « parente », ensuite celle qui la référence.** (Ex. `seance_user` viendra après `seances` **et** `users`.)

## ⚠️ Les pièges qui piquent au début

1. **Recréer une table que Laravel crée déjà pour toi.** Depuis Laravel 11, le premier fichier `0001_01_01_000000_create_users_table.php` crée **trois** tables d'un coup : `users`, `password_reset_tokens` **et** `sessions`. Idem pour `cache` et `jobs` dans les fichiers suivants. (Le préfixe `0001_01_01` est une date volontairement ancienne pour que ces tables de base passent **toujours en premier**.)

   **Vécu sur ce projet, deux fois :**
   - `php artisan session:table` lancé parce que `SESSION_DRIVER=database` → ça génère un `create_sessions_table` **daté d'aujourd'hui**… alors que `sessions` est **déjà** créée dans `create_users_table`. Deux fichiers créent la même table.
   - `make:migration create_places_table` alors que le model `Place` avait déjà généré sa migration → même doublon.

   ⚠️ **Le piège dans le piège :** la commande de génération **ne dit rien** — elle crée le fichier sans vérifier que la table existe. L'erreur « **table `sessions` already exists** » n'arrive qu'**au moment du `migrate`**, pas à la création. D'où la surprise : tu crois que c'est bon, et ça plante deux commandes plus tard.

   **Réflexe : avant tout `session:table` / `make:migration create_X_table`, ouvre les migrations existantes et vérifie que `X` n'y est pas déjà.** Si le doublon est déjà généré mais pas encore migré : supprime simplement le fichier en trop.
2. **Oublier une colonne dans `$fillable`** → `MassAssignmentException` au `create()`. Ce n'est pas un bug : c'est le garde-fou qui fait son travail.
3. **Attendre que le model liste ses colonnes** comme un `schema.prisma` : non, il ne les connaît qu'à l'exécution, via la table. La source de vérité, ce sont les migrations.
4. **Modifier une migration déjà appliquée** en espérant que ça se propage : non. Une fois `migrate` passé, tu crées une **nouvelle** migration (ou tu refais `migrate:fresh` en dev).

## 🧰 Pense-bête avant tout `migrate` sur un nouveau projet

- [ ] La table que je veux créer n'existe **pas déjà** dans les migrations par défaut (`users` + `sessions` + `password_reset_tokens`, `cache`, `jobs`) ni ailleurs → sinon doublon.
- [ ] Table **parente créée avant** la table qui la référence (ordre des horodatages).
- [ ] Je suis bien en **local** si je tape `migrate:fresh` — jamais sur une base partagée.
- [ ] Sur un env partagé : uniquement `migrate` (jamais `:fresh`), et `db:seed` seulement si c'est voulu.
- [ ] Après un changement de structure, je crée une **nouvelle** migration plutôt que d'éditer une déjà appliquée.

➡️ Suite : [Cours 4 — Routing, Controllers & validation](04-routing-controllers-validation.md)
