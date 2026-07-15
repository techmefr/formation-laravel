# Cours 9 — Les packages du projet, comment ça marche

> Objectif : comprendre **ce que fait chaque package** et **comment il marche sous le capot**, pas juste comment l'installer. Les commandes d'install/config sont dans [XEFI 02](xefi-02-packages.md) ; ici on veut le modèle mental. Tu viens de JS/TS — on relie chaque outil à ce que tu connais.

---

## 0. Le kit de survie (à lire en premier)

Les packages du projet, chacun résout **un** problème précis (les 5 premiers sont **imposés**, le dernier est un **bonus**) :

| Package | Le problème qu'il résout | Analogie côté JS |
|---|---|---|
| **spatie/laravel-permission** | Qui a le droit de faire quoi (rôles + permissions) | CASL, ou les policies Supabase (RLS) |
| **spatie/laravel-medialibrary** | Attacher des fichiers à un model, stockés sur S3 | un service d'upload type Uploadthing / S3 SDK |
| **tymon/jwt-auth** | Authentifier une API sans session (token) | l'auth JWT de Supabase, côté serveur cette fois |
| **lomkit/laravel-rest-api** | Générer une API CRUD riche sans écrire 20 controllers | un peu comme PostgREST / Hasura : tu déclares, l'API existe |
| **xefi/faker-php** | Remplir la base de fausses données réalistes | `@faker-js/faker` dans tes tests |
| **spatie/laravel-activitylog** *(bonus)* | Tracer qui a créé / modifié / supprimé quoi | un journal d'audit, façon triggers d'audit SQL |

Le point commun de la mécanique Laravel : **presque tout se branche via un trait sur un model** (`use HasRoles;`, `use InteractsWithMedia;`…). Le trait « colle » des méthodes et des relations sur ton model (revois le Cours 1 §10 si « trait » est encore flou).

---

## 1. `spatie/laravel-permission` — qui a le droit de quoi

### Le problème sans lui

Tu pourrais mettre une colonne `role` sur `users` (`'admin'`, `'coach'`…). Mais dès que les droits se croisent (« un coach peut créer, un admin peut tout »), tu te retrouves avec des `if` en dur partout. Ce package externalise **rôles** et **permissions** dans la base, proprement.

### Comment ça marche

Il crée **5 tables** :

| Table | Contenu |
|---|---|
| `roles` | la liste des rôles (`admin`, `coach`, `collaborator`) |
| `permissions` | la liste des permissions (`create seances`, `delete seances`…) |
| `role_has_permissions` | quel rôle a quelles permissions (pivot) |
| `model_has_roles` | quel user a quels rôles (pivot **polymorphe**) |
| `model_has_permissions` | permissions données directement à un user (pivot polymorphe) |

Le trait `HasRoles` sur `User` ajoute les relations vers ces tables **et** les méthodes (`assignRole`, `hasRole`, `can`, `givePermissionTo`…).

```php
class User extends Authenticatable
{
    use HasRoles;   // ← branche tout le mécanisme sur le model
}

$user->assignRole('coach');        // écrit une ligne dans model_has_roles
$user->can('create seances');      // remonte user → rôles → permissions
```

> 💡 « Polymorphe » veut juste dire que le pivot stocke *le type de model* en plus de l'id (`model_type = App\Models\User`). Comme ça le même système marcherait sur un autre model que `User`. C'est le `@relation` générique que tu n'as pas à écrire.

Deux détails de fonctionnement qui surprennent :

- **Le cache.** Les permissions sont mises en cache pour la perf. Si tu changes des permissions en base à la main, fais `sail artisan permission:cache-reset`, sinon tu ne vois pas le changement.
- **L'intégration au `Gate` Laravel.** Comme le package se branche sur le système d'autorisation natif, `$user->can('...')`, le middleware `permission:...` et le `@can` en Blade marchent automatiquement (revois le [Cours 6](06-auth-permissions.md)).

> 🔴 **Convention XEFI** : dans le code, on teste une **permission** (`can('delete seances')`), jamais un rôle (`hasRole('admin')`). Le package sait faire les deux ; la convention impose le premier.

---

## 2. `spatie/laravel-medialibrary` — les fichiers d'un model

### Le problème sans lui

Gérer des uploads à la main = stocker le fichier quelque part, garder son chemin en base, gérer les URLs, les miniatures, la suppression… répétitif et cassant. Ce package fait tout ça, et découple **le fichier** de **son emplacement**.

### Comment ça marche

Il crée **une table `media`** (polymorphe elle aussi) : chaque ligne = un fichier rattaché à un model (`model_type` + `model_id`), avec son nom, sa taille, sa collection, et **le disque** où il est physiquement stocké.

Le fichier lui-même n'est PAS en base : il part sur un **disque** Laravel (`local`, ou `s3` = MinIO/RustFS chez toi). La base ne garde que les métadonnées + l'emplacement.

```php
class Seance extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')->useDisk('s3');  // range dans MinIO
    }
}

$seance->addMediaFromRequest('file')->toMediaCollection('files'); // upload + ligne media
$seance->getMedia('files');                                       // Collection de fichiers
```

> 💡 Le **disque** (`local`, `s3`) est une abstraction Laravel : ton code dit « range ça dans `files` », et c'est la config qui décide *où physiquement* (disque local en dev, S3 en prod). Comme un adapter de storage : tu changes le backend sans toucher au code métier.

- **Collections** = des « dossiers logiques » par model (`files`, `avatars`…). Tu peux avoir plusieurs collections sur un même model.
- **Conversions** (miniatures, redimensionnements) : le package peut les générer, souvent **en tâche de fond via la queue** (revois le Cours 7 : `ShouldQueue`). D'où l'intérêt d'avoir `queue:work` qui tourne.

> Chez toi, MinIO joue le rôle de S3 en local. Console : `http://localhost:8900`. Pense à créer le bucket (`local`) sinon l'upload échoue.

---

## 3. `tymon/jwt-auth` — l'authentification par token

### Le problème sans lui

L'auth web de Laravel repose sur une **session + cookie** : le serveur se souvient de toi. Pour une API consommée par un front séparé (Partie II), on ne veut pas de session : chaque requête doit se suffire à elle-même. D'où le **token JWT**.

### Comment ça marche

Un **JWT** est une chaîne en 3 parties séparées par des points : `header.payload.signature`.
- **header** — l'algo de signature.
- **payload** — les données (l'id du user, l'expiration…). **Lisible** (base64, pas chiffré) — donc on n'y met jamais de secret.
- **signature** — le payload signé avec **ta clé serveur** (`JWT_SECRET`, posée par `jwt:secret`).

Le serveur n'a rien à stocker : il **re-signe** le payload reçu et compare à la signature. Si ça matche, le token est authentique et non falsifié.

```php
// À la connexion : on signe un token
$token = auth('api')->attempt(['email' => ..., 'password' => ...]);

// À chaque requête protégée : le client renvoie
// Authorization: Bearer <token>
// et le guard auth:api vérifie la signature
```

Deux pièces à brancher (voir [Cours 8](08-api-rest-jwt-lomkit.md) et XEFI 02) :
- un **guard `api`** en driver `jwt` dans `config/auth.php` ;
- le model `User` implémente **`JWTSubject`** (deux méthodes : comment m'identifier dans le token, quelles données ajouter).

> 💡 C'est exactement le modèle Supabase que tu connais côté front (le `Bearer` dans le header), mais vu **depuis le serveur** : ici c'est toi qui signes le token à la connexion et qui le vérifies à chaque appel. `implements JWTSubject` = le même `implements` TypeScript du Cours 1 : un contrat que le model remplit pour la lib.

> ⚠️ Le payload est **lisible par n'importe qui** (base64, pas chiffré). La signature garantit qu'il n'a pas été **modifié**, pas qu'il est **secret**. Ne mets jamais de donnée sensible dedans.

---

## 4. `lomkit/laravel-rest-api` — l'API industrialisée

### Le problème sans lui

Écrire à la main chaque endpoint (liste, filtres, tri, pagination, relations, création en masse…) pour chaque ressource, c'est 20 controllers quasi identiques. lomkit génère cette API riche à partir d'**une classe de config**.

### Comment ça marche

Le client n'appelle pas 15 URLs différentes : il tape **deux endpoints génériques** et décrit ce qu'il veut en **JSON** :
- **`search`** (lecture) — filtres, tris, includes de relations, pagination.
- **`mutate`** (écriture) — créer / mettre à jour, y compris des relations imbriquées, en masse.

Côté serveur, tu écris une **Resource lomkit** qui **déclare ce qui est autorisé** : quels champs sont exposés, filtrables, quelles relations, quelles permissions.

```php
class SeanceResource extends Resource
{
    public static $model = Seance::class;

    public function fields(RestRequest $request): array    { return ['id', 'name', 'started_at']; }
    public function filters(RestRequest $request): array   { return [/* champs filtrables */]; }
    public function relations(RestRequest $request): array { return [/* relations exposées */]; }
}
```

> 💡 Le renversement mental : tu passes de « j'écris chaque endpoint » à « je **déclare** ce que le client a le droit de demander ». C'est de la config, pas de l'impératif — dans l'esprit de PostgREST ou Hasura que tu as pu croiser : tu décris le modèle, l'API en découle. Ce qui n'est pas listé dans `fields()`/`filters()`/`relations()` **n'existe pas** pour le client.

> ⚠️ lomkit génère les endpoints, **pas la sécurité**. Les permissions et Policies du [Cours 6](06-auth-permissions.md) restent **obligatoires** : sans elles, tu exposes un CRUD ouvert à tous. Tu branches tes autorisations dans la Resource.

---

## 5. `xefi/faker-php` — des données réalistes pour tester

### Le problème sans lui

Une appli vide n'est pas démontrable ni testable. Il faut des users, des rôles, des séances, des inscriptions… crédibles. Écrire tout ça à la main est fastidieux ; un générateur de fausses données le fait pour toi.

### Comment ça marche

C'est le **faker maison XEFI** : il remplace `fakerphp/faker` et fournit des données plus réalistes/localisées. Tu l'utilises dans les **factories** (le « moule » d'une donnée fictive, vu au [Cours 5](05-relations-eloquent.md)), et les **seeders** appellent ces factories pour peupler la base.

```php
// database/factories/SeanceFactory.php
public function definition(): array
{
    return [
        'name'       => fake()->words(2, true),
        'started_at' => now()->addDays(rand(1, 30)),
        'coach_id'   => User::factory(),
    ];
}
```

```bash
sail artisan migrate:fresh --seed   # recrée la base + lance les seeders
```

> 💡 C'est ton `@faker-js/faker` : le même rôle (fausses données pour tests/démos), juste côté PHP. Installé en `--dev` car il n'a rien à faire en production.

> 🎯 L'objectif du projet : `migrate:fresh --seed` doit produire une appli **complète et démontrable** (users avec rôles, séances, inscriptions) en une commande.

---

## 6. `spatie/laravel-activitylog` — tracer qui a fait quoi *(bonus, non imposé)*

### Le problème sans lui

Tu veux savoir **qui** a modifié une séance, **quand**, et **quoi** a changé (audit). Le faire à la main = ajouter du code de traçage à chaque endroit sensible. Ce package le fait à ta place.

### Comment ça marche

Point important qui répond à la question qu'on se pose toujours : **tu ne définis aucune colonne**. Le package crée **une seule table `activity_log`** au schéma **fixe**, la même pour tous les models :

| Colonne | Contenu |
|---|---|
| `log_name`, `description`, `event` | le « quoi » (ex. `updated`) |
| `subject_type` + `subject_id` | **sur quel model** (polymorphe : `App\Models\Seance` #12) |
| `causer_type` + `causer_id` | **qui** a agi (le user connecté, auto-détecté) |
| `properties` | un **JSON** qui absorbe les attributs changés / tes données custom |

> 💡 Les valeurs métier ne vont pas dans des colonnes dédiées : elles atterrissent dans le **JSON `properties`**. Donc **aucune migration par model** — la même table encaisse une `Seance`, un `User`, n'importe quoi. Pense à une table d'événements générique avec un `payload` libre.

En revanche, **ce qui est logué, c'est toi qui le déclares** — rien ne se trace tout seul. Deux façons :

**1. Automatique sur un model** — le trait `LogsActivity` + tu choisis les champs suivis :

```php
class Seance extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'started_at', 'max_participants'])  // ← TU choisis les champs
            ->logOnlyDirty();      // ne logue que ce qui a réellement changé
    }
}
```

**2. Manuel** — pour un événement précis :

```php
activity()->performedOn($seance)->causedBy($user)
    ->withProperties(['raison' => 'annulation'])->log('Séance annulée');
```

> ⚠️ Sans le trait `LogsActivity` (avec son `getActivitylogOptions`) **ni** appel manuel à `activity()`, **rien** n'est enregistré. Le stockage est automatique ; le déclenchement et le contenu, c'est toi qui les décides.

---

## À retenir

- **spatie/laravel-permission** — rôles + permissions en base (5 tables), branché via `HasRoles` ; s'intègre au `Gate` natif (`can`, middleware, `@can`). Convention XEFI : tester la **permission**, pas le rôle.
- **spatie/laravel-medialibrary** — fichiers rattachés à un model (table `media` polymorphe), stockés sur un **disque** (S3/MinIO), pas en base ; collections + conversions via la queue.
- **tymon/jwt-auth** — auth **stateless** par token signé (`header.payload.signature`) ; rien à stocker côté serveur, le model implémente `JWTSubject`. Payload lisible, jamais secret.
- **lomkit/laravel-rest-api** — API générée via `search`/`mutate` en JSON ; tu **déclares** le permis dans une Resource. Ne dispense pas d'autoriser.
- **xefi/faker-php** — faker maison pour factories/seeders ; `--dev` ; objectif `migrate:fresh --seed` démontrable.
- **spatie/laravel-activitylog** *(bonus)* — audit dans **une** table `activity_log` (schéma fixe, valeurs dans le JSON `properties`) ; rien n'est tracé sans le trait `LogsActivity` ou un `activity()->log()` explicite.

## ⚠️ Les pièges qui piquent au début

1. **Croire qu'un trait suffit sans migration.** `use HasRoles;` ou `use InteractsWithMedia;` ajoutent les *méthodes*, mais les *tables* (`roles`, `media`…) viennent d'une migration à publier + `migrate`. Trait **et** migration, comme pour SoftDeletes.
2. **Ne pas voir un changement de permission** : c'est le **cache** de spatie. `sail artisan permission:cache-reset`.
3. **Upload qui échoue en silence** : le bucket MinIO n'existe pas, ou `queue:work` ne tourne pas (les conversions partent en queue). Vérifie les deux.
4. **Mettre une donnée sensible dans un JWT** : le payload est lisible (base64). La signature protège de la falsification, pas de la lecture.
5. **Exposer un CRUD lomkit sans permissions** : `search`/`mutate` marchent tout seuls, mais sans Policies tu ouvres tout. La sécurité reste à ta charge.

➡️ Pour l'installation et la config pas à pas : [XEFI 02 — Packages imposés](xefi-02-packages.md) · Retour au [sommaire](README.md)
