# XEFI 02 — Les packages imposés

> Ce que tu installeras sur le projet, dans l'ordre. Pour chacun : **à quoi ça sert**, **comment l'installer**, **l'usage XEFI**. Rien d'optionnel ici — ces packages sont imposés.

Rappel : toute commande passe par Sail → `sail composer require …`, `sail artisan …`.

---

## Partie I — Application web

### 1. `spatie/laravel-permission` — rôles & permissions

**Rôle.** Gérer les 3 rôles (`admin`, `coach`, `collaborator`) et les permissions. Rappel convention : dans le code on teste des **permissions**, pas des rôles ([XEFI 01 §2](xefi-01-conventions.md)).

```bash
sail composer require spatie/laravel-permission
sail artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
sail artisan migrate
```

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable { use HasRoles; }
```

```php
// Seeder : créer permissions + rôles
$create = Permission::firstOrCreate(['name' => 'create seances']);
$delete = Permission::firstOrCreate(['name' => 'delete seances']);

Role::firstOrCreate(['name' => 'admin'])->givePermissionTo(Permission::all());
Role::firstOrCreate(['name' => 'coach'])->givePermissionTo($create);
Role::firstOrCreate(['name' => 'collaborator']);   // aucune permission d'écriture
```

> Après avoir modifié permissions/rôles en base : `sail artisan permission:cache-reset`.

---

### 2. `spatie/laravel-medialibrary` — fichiers sur MinIO/RustFS (S3)

**Rôle.** Attacher des fichiers à un model (le champ `files` d'une séance), stockés sur **MinIO/RustFS** en S3 (pas sur le disque local).

```bash
sail composer require spatie/laravel-medialibrary
sail artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
sail artisan migrate
```

```php
// app/Models/Seance.php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Seance extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')->useDisk('s3');   // disque S3 = MinIO
    }
}
```

```php
// Ajouter / lire un fichier
$seance->addMediaFromRequest('file')->toMediaCollection('files');
$urls = $seance->getMedia('files')->map->getUrl();
```

Config S3 (MinIO) dans `.env` (Sail expose MinIO en local) :

```dotenv
FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://minio:9000
AWS_ACCESS_KEY_ID=sail
AWS_SECRET_ACCESS_KEY=password
AWS_BUCKET=local
AWS_USE_PATH_STYLE_ENDPOINT=true
```

> Console MinIO en local : `http://localhost:8900`. Pense à créer le bucket `local`.

---

### 3. `spatie/laravel-activitylog` — audit / logs

**Rôle.** Tracer les actions sur les models (qui a créé/modifié/supprimé quoi). Utile pour l'audit.

```bash
sail composer require spatie/laravel-activitylog
sail artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
sail artisan migrate
```

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Seance extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'started_at', 'max_participants']);
    }
}
```

---

### 4. `spatie/laravel-sluggable` — slugs

**Rôle.** Générer un slug URL-friendly depuis un champ (ex. `name` → `yoga-du-matin`).

```bash
sail composer require spatie/laravel-sluggable
```

```php
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Seance extends Model
{
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string { return 'slug'; }  // route model binding par slug
}
```

> Ajoute une colonne `slug` (string, unique) dans la migration.

---

### 5. `xefi/faker-php` — seeding réaliste

**Rôle.** Le faker maison XEFI (remplace `fakerphp/faker`), pour des données réalistes. Utilisé dans les factories/seeders.

```bash
sail composer require xefi/faker-php --dev
```

Objectif : `migrate:fresh --seed` doit produire une appli complète et démontrable (users avec rôles, séances, inscriptions).

---

## Partie II — API REST

### 6. `tymon/jwt-auth` — authentification par token

**Rôle.** Sécuriser l'API en *stateless* : chaque requête porte un JWT (`Authorization: Bearer …`).

```bash
sail composer require tymon/jwt-auth
sail artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
sail artisan jwt:secret
```

```php
// config/auth.php — guard api en JWT
'guards' => [
    'api' => ['driver' => 'jwt', 'provider' => 'users'],
],
```

```php
// app/Models/User.php implémente JWTSubject
public function getJWTIdentifier() { return $this->getKey(); }
public function getJWTCustomClaims(): array { return []; }
```

---

### 7. `lomkit/laravel-rest-api` — industrialisation de l'API

**Rôle.** Générer une API riche (filtres, tri, pagination, relations, opérations en masse) via des **Resources**, plutôt que 20 controllers à la main. Standard StackTim.

```bash
sail composer require lomkit/laravel-rest-api
sail artisan rest:resource SeanceResource
```

Le client décrit sa requête en JSON via `search` (lecture) et `mutate` (écriture) ; côté serveur tu déclares le permis (champs filtrables, relations, permissions) dans la Resource. Voir [Cours 8](08-api-rest-jwt-lomkit.md).

> ⚠️ Les **permissions/Policies** restent obligatoires : lomkit ne dispense pas d'autoriser.

---

## Outils qualité & debug (rappel [XEFI 01](xefi-01-conventions.md))

| Package | Install | Usage |
|---|---|---|
| **Larastan** | `sail composer require --dev larastan/larastan` | analyse statique, **niveau ≥ 5** |
| **Pint** | inclus avec Laravel | `sail pint` (formatage), `pint.json` à la racine |
| **Telescope** | `--dev` | debug **dev/staging seulement** |
| **Pulse** | prod | métriques prod |
| **Horizon** | si queues Redis | dashboard des queues |

> 💡 **Telescope ≠ Pulse** — pas le même outil en deux modes, mais **deux jobs différents**.
> **Telescope** est un enregistreur *fin, par requête* (chaque query SQL avec bindings, l'exception + sa stack, mails, jobs, cache…) → il répond à « qu'est-ce qui s'est passé **exactement** dans cette requête ? ». Lourd (une ligne par événement) et capture des données sensibles → **dev/staging seulement**.
> **Pulse** est un tableau de bord *agrégé et léger* (requêtes/jobs lents, top exceptions, top utilisateurs, santé serveur) → il répond à « comment se porte l'appli **globalement** ? ». Fait pour la **prod**.
> On ne peut donc pas « faire juste Pulse avec des env différents » : Pulse n'enregistre pas le détail par requête (tu perdrais l'outil de debug), et Telescope n'a rien à faire en prod (coût + fuite de données). Analogie JS : Telescope ≈ tes DevTools/APM verbeux en local, Pulse ≈ un dashboard type Grafana/Sentry-overview.

---

## Ordre d'installation conseillé sur le projet

1. Scaffold Laravel + Sail (MySQL, Redis, Mailpit, MinIO)
2. `laravel-permission` → rôles/permissions + seeder
3. Model `Seance` + migration + relations
4. `medialibrary` (MinIO) pour les fichiers
5. `activitylog`, `sluggable`
6. `xefi/faker-php` → factories/seeders complets
7. Notifications + Listeners (Mailpit)
8. Larastan + Pint (verts avant commit)
9. **Partie II** : `tymon/jwt-auth` puis `lomkit/laravel-rest-api`

➡️ Suite : [XEFI 03 — Recettes du projet « Séances de sport »](xefi-03-recettes-projet.md)
