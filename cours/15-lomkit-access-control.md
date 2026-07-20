# Cours 15 — lomkit/laravel-access-control (Controls/Perimeters)

> But : remplacer les Policies écrites à la main (cours 6, 11, 12, 14) par le mécanisme **Controls/Perimeters** de lomkit, comme en prod sur `platform-api` (cours 6 et 8, section « en vrai chez StackTim »). Package séparé de `lomkit/laravel-rest-api` (cours 14) : les deux se combinent, mais s'installent et se pensent indépendamment.
>
> ⚠️ Au moment d'écrire ce cours, `lomkit/laravel-access-control` est en **bêta** — la doc officielle déconseille elle-même son usage en prod tel quel. On l'utilise ici pour comprendre le mécanisme, pas comme une recommandation de mise en prod immédiate.

---

## 1. Le problème que ça résout

Avec les Policies à la main (cours 6/11/12), chaque règle d'accès est un `if` écrit une fois par méthode, par policy :

```php
// SeancePolicy — cours 11
public function update(User $user, Seance $seance): bool
{
    return $user->can('update seances') && $this->ownsOrManages($user, $seance);
}
```

Ça marche, mais deux problèmes à l'échelle d'un vrai projet StackTim :
1. La même logique ("les siennes" vs "toutes") se répète policy par policy, méthode par méthode (`update`, `delete`, `cancel`...).
2. Une Policy n'agit que sur un **modèle en main** (`$seance` déjà chargé) — elle ne sait pas filtrer une liste. Pour qu'un manager ne voie QUE ses séances dans un `index`, il faut un `where()` séparé, écrit à la main, à tenir synchronisé avec la Policy.

**Controls/Perimeters** centralise les deux dans un seul endroit : une règle "qui a accès à quoi" qui s'applique aussi bien à une autorisation ponctuelle (`$user->can('update', $seance)`) qu'à un filtrage de liste (`Seance::controlled()->get()`).

---

## 2. Le vocabulaire : Perimeter, Control, Policy

| Terme | Rôle |
|---|---|
| **Perimeter** | Un périmètre d'accès possible (ex. `GlobalPerimeter` = accès à tout, `OwnPerimeter` = accès à ce qui m'appartient). Réutilisable entre plusieurs models. |
| **Control** | La liste des Perimeters valables pour **un** model donné (ex. `SeanceControl` combine `GlobalPerimeter` pour l'admin et `OwnPerimeter` pour le coach). |
| **`ControlledPolicy`** | Une Policy Laravel qui délègue tout à son Control, au lieu d'écrire `viewAny`/`view`/`update`... à la main. |

Chaque Perimeter définit trois choses :

| Méthode | Répond à |
|---|---|
| `allowed(fn(User $user, string $method))` | Est-ce que cet utilisateur a accès à CE périmètre, pour cette action ? (ex. a-t-il la permission `update own models`) |
| `should(fn(User $user, Model $model))` | Ce modèle précis rentre-t-il dans ce périmètre pour cet utilisateur ? (ex. `$seance->coach_id === $user->id`) |
| `query(fn(Builder $query, User $user))` | Comment filtrer une liste pour ce périmètre ? (ex. `->where('coach_id', $user->id)`) |

Un Control peut avoir plusieurs Perimeters (admin = `Global`, coach = `Own`) : le premier dont `allowed()` passe est retenu (détail de l'ordre d'exécution en section 7).

---

## 3. Mise en place, étape par étape

```bash
composer require lomkit/laravel-access-control

sail artisan make:perimeter GlobalPerimeter
sail artisan make:perimeter OwnPerimeter
sail artisan make:control SeanceControl
```

**Le Control**, calqué sur `SeancePolicy::ownsOrManages()` déjà écrite (cours 11) :

```php
// app/Access/Controls/SeanceControl.php
class SeanceControl extends Control
{
    protected string $model = Seance::class;

    protected function perimeters(): array
    {
        return [
            // Admin/manager : accès à toutes les séances.
            GlobalPerimeter::new()
                ->allowed(function (User $user, string $method) {
                    if (! $user->hasRole(['admin', 'manager'])) {
                        return false;
                    }

                    return $this->allowedForMethod($user, $method);
                })
                ->should(fn (User $user, Model $model) => true)
                ->query(fn (Builder $query, User $user) => $query),

            // Coach/collaborateur : uniquement ses propres séances.
            OwnPerimeter::new()
                ->allowed(fn (User $user, string $method) => $this->allowedForMethod($user, $method))
                ->should(fn (User $user, Seance $model) => $model->coach_id === $user->id)
                ->query(fn (Builder $query, User $user) => $query->where('coach_id', $user->id)),
        ];
    }

    private function allowedForMethod(User $user, string $method): bool
    {
        // viewAny/view restent ouverts à tout le monde, comme dans l'ancienne Policy.
        if (in_array($method, ['viewAny', 'view'], true)) {
            return true;
        }

        return $user->can("{$method} seances");
    }
}
```

> 💡 On retrouve exactement la logique de `ownsOrManages()` (`hasRole(['admin', 'manager']) || $seance->coach_id === $user->id`) — mais éclatée en deux Perimeters au lieu d'un seul booléen. C'est ce découpage qui permet de la réutiliser aussi pour filtrer une liste (`query()`), ce qu'un simple `if` dans une Policy ne sait pas faire.
>
> ⚠️ **Piège** : `allowed()` reçoit `$method` déjà mappé (`config('access-control.methods.*)`, avec `viewAny` → `view`) — dans les deux cas tu reçois `'view'`. Sans le `if` ci-dessus, `viewAny`/`view` seraient soumis à la même règle que `create`/`update`/`delete` : ni les policies web ni l'API n'auraient plus fonctionné pour un simple `index`.

**Le modèle** : ajoute `HasControl`.

```php
// app/Models/Seance.php
use Lomkit\Access\Controls\HasControl;

class Seance extends Model implements HasMedia
{
    use HasControl, HasFactory, InteractsWithMedia, SoftDeletes;
}
```

**La Policy** délègue au Control — plus de `viewAny`/`create`/`update`/`delete` à écrire à la main. Attention au nom de la propriété : c'est **`$control`** (pointant vers la classe Control), pas `$model` :

```php
// app/Policies/SeancePolicy.php
use Lomkit\Access\Policies\ControlledPolicy;

class SeancePolicy extends ControlledPolicy
{
    protected string $control = SeanceControl::class;
}
```

`viewAny`, `create`, `update`, `delete`, `restore`, `forceDelete` sont couvertes automatiquement par ce mécanisme — cf. cours 14 : c'est exactement les méthodes que lomkit/laravel-rest-api appelle déjà via `Gate::inspect(...)`, donc **le CRUD API du cours 14 continue de marcher sans y toucher**.

⚠️ **`view` fait exception** : l'ancienne Policy le laissait ouvert à tout le monde sur un modèle précis (pas de notion de "mes séances" pour consulter le détail d'une séance). Mais `should()` d'`OwnPerimeter` compare `$model->coach_id === $user->id` — sans garde-fou, un coach se verrait refuser `view` sur la séance d'un autre coach, une régression par rapport à l'ancien comportement. Fix : override `view()` directement dans la Policy, avant même que le Control soit consulté — et attention à la signature, elle doit rester compatible avec celle de `ControlledPolicy` (paramètres `Model`, pas `Seance` ni `User`, sinon PHP refuse l'héritage) :

```php
public function view(Model $user, Model $model): bool
{
    return true;
}
```

---

## 4. Filtrer une liste : le scope `controlled()`

C'est le vrai gain par rapport aux Policies à la main :

```php
// avant (cours 12, à la main) :
Seance::query()->when(! $user->hasRole(['admin', 'manager']), fn ($q) => $q->where('coach_id', $user->id))->get();

// après :
Seance::controlled()->get();
```

`controlled()` applique automatiquement le `query()` du premier Perimeter qui matche l'utilisateur courant — le SQL exact généré dépend de son rôle, sans `if` à écrire dans le controller/service.

⚠️ Point relevé dans la doc : sur une requête (`query()`), Access Control appelle toujours `should()` avec la méthode `view` (logique : lire une liste = "voir" chaque ligne). Sur un `index` de controller qui fait à la fois `$this->authorize('viewAny', ...)` (Policy) **et** `Model::controlled()` (Query), le contrôle se déclenche deux fois — pas un bug, juste deux couches qui répondent à deux questions différentes ("puis-je lister ?" vs "quelles lignes ?").

⚠️ **Autre piège, plus sournois** : `controlled()` passe **toujours** par les Perimeters du Control — le `view()` qu'on vient de surcharger dans la Policy (section 3) n'a strictement aucun effet ici, parce que `controlled()` ne consulte jamais la Policy. Testé sur ce projet : un collaborateur (aucun perimeter ne le concerne — ni admin/manager, ni coach) obtient **0 résultat** via `Seance::controlled()->get()`, alors que le calendrier actuel de l'app montre toutes les séances à tout le monde. C'est cohérent avec la logique Own/Global (les mêmes règles que `update`/`delete`), mais ça ne reproduit pas le comportement réel du listing existant. **On n'a donc pas branché `controlled()` sur `SeanceController`/`CalendarController`** — à réserver au jour où le listing lui-même doit être restreint par propriétaire, avec un perimeter dédié si besoin (ex. un `AnyonePerimeter` avec `query()` qui ne filtre rien, en plus de `Global`/`Own`).

---

## 5. Ce que ça remplace, ce que ça ne remplace pas

- **Remplace** : les méthodes CRUD standards des Policies (`viewAny`/`view`/`create`/`update`/`delete`) et les `where()` de filtrage par rôle écrits à la main dans les controllers/services.
- **Ne remplace pas** : les actions métier custom (`cancel`, `manageParticipants` du cours 12/14) — ce sont des ability names hors du contrat `ControlledPolicy`, donc `SeancePolicy` garde ces deux méthodes écrites à la main, en plus de `extends ControlledPolicy` :

```php
class SeancePolicy extends ControlledPolicy
{
    protected string $control = SeanceControl::class;

    public function view(Model $user, Model $model): bool
    {
        return true;
    }

    public function cancel(User $user, Seance $seance): bool
    {
        return $user->can('cancel seances') && $this->ownsOrManages($user, $seance);
    }

    public function manageParticipants(User $user, Seance $seance): bool
    {
        return $user->can('manage participants') && $this->ownsOrManages($user, $seance);
    }

    private function ownsOrManages(User $user, Seance $seance): bool
    {
        return $user->hasRole(['admin', 'manager']) || $seance->coach_id === $user->id;
    }
}
```

> 💡 Les policies `UserPolicy`/`PlacePolicy` (cours 14, nécessaires pour les relations `coach`/`place` en API) peuvent rester de simples Policies classiques — Controls/Perimeters n'a de sens que là où la règle "qui a accès à quoi" varie par rôle, ce qui n'est pas le cas ici (`viewAny` = `true` pour tout le monde).

---

## 6. Deux designs possibles pour "own vs any" — et pourquoi on a choisi celui-ci

Avant de coder, la question naturelle est : comment distinguer "un coach ne modifie que ses séances" de "un admin modifie tout" ? Il y a deux façons légitimes de répondre, et ce n'est pas Laravel qui tranche — c'est un choix d'architecture.

### Design A — deux permissions distinctes (l'intuition la plus directe)

```php
// Seeder
Permission::create(['name' => 'update own seances']);
Permission::create(['name' => 'update any seances']);

Role::findByName('coach')->givePermissionTo('update own seances');
Role::findByName('admin')->givePermissionTo('update any seances');
```

```php
// SeancePolicy
public function update(User $user, Seance $seance): bool
{
    if ($user->can('update any seances')) {
        return true;
    }

    return $user->can('update own seances') && $seance->coach_id === $user->id;
}
```

C'est un **bon** design — clair, explicite, chaque permission a un nom qui dit exactement ce qu'elle autorise. C'est probablement ce à quoi on pense en premier quand on découvre spatie/laravel-permission.

### Design B — une seule permission + Perimeters (celui qu'on a implémenté)

```php
// Seeder — une seule permission, partagée par admin/manager/coach
Permission::create(['name' => 'update seances']);
```

```php
// SeanceControl
GlobalPerimeter::new()
    ->allowed(fn ($user, $method) => $user->hasRole(['admin', 'manager']) && $user->can("{$method} seances"))
    ->should(fn ($user, $model) => true),

OwnPerimeter::new()
    ->allowed(fn ($user, $method) => $user->can("{$method} seances"))
    ->should(fn ($user, $model) => $model->coach_id === $user->id),
```

Ici, la permission `update seances` répond juste à "cet utilisateur a-t-il le droit de toucher à des séances, en général ?" — la portée (own/any) n'est **pas** dans le nom de la permission, elle est dans **quel Perimeter matche**, déterminé par le rôle (`hasRole`) ou l'appartenance (`coach_id`).

### Ce qui a changé, concrètement

| | Design A (permissions) | Design B (Perimeters) — implémenté |
|---|---|---|
| Nombre de permissions par action | 2 (`xxx own`, `xxx any`) | 1 (`xxx`) |
| Où vit la règle "own vs any" | Dans le nom de la permission + un `if` dans la Policy | Dans le Perimeter (`should()`/`query()`), hors Policy |
| Réutilisation sur une nouvelle action (`cancel`, `manageParticipants`...) | Recréer une paire de permissions + un `if` à chaque fois | Aucune permission à ajouter ; nouveaux Perimeters seulement si la logique "own/any" change |
| Filtrage d'une liste (`index`) | Pas automatique — un `where()` à écrire à part, à tenir synchronisé | Automatique via `Model::controlled()`, même règle que l'autorisation |

### Pourquoi B est plus pertinent ici

Pas parce que A serait "faux" — mais parce que dans ce projet, la règle "own vs any" est **la même pour toutes les actions CRUD** (`create`/`update`/`delete` suivent tous `hasRole(['admin','manager']) || coach_id === user.id`, cf. `ownsOrManages()` du cours 11). Avec le design A, tu répéterais cette paire de permissions et ce `if` pour chaque action. Avec le design B, tu l'écris **une fois** dans `SeanceControl`, et chaque nouvelle action CRUD standard (`viewAny`, `create`, `update`, `delete`...) en hérite automatiquement — c'est exactement le problème identifié en section 1 ("la même logique se répète policy par policy, méthode par méthode").

Le vrai signal pour choisir : **est-ce que la portée "own vs any" varie selon l'action, ou est-ce toujours la même règle ?** Si elle varie (ex. un rôle qui peut *voir* toutes les séances mais *modifier* seulement les siennes — ce qui est justement notre cas, cf. section 3, `viewAny`/`view` toujours ouverts), le design B s'en sort très bien aussi : c'est le `$method` reçu par `allowed()` qui permet de faire varier la règle par action, sans dédoubler les permissions.

Le design A reste pertinent si la portée "own/any" doit être **configurable indépendamment par action et par rôle** de façon fine (ex. un rôle qui a "own" sur `update` mais "any" sur `view`, sans logique commune factorisable) — dans ce cas, des permissions explicites nommées sont plus lisibles qu'un Perimeter qui devient un gros `switch` sur `$method`.

---

## 7. L'ordre d'exécution réel : `allowed()` puis `should()` **OU** `query()`, jamais les deux

Piège de lecture fréquent : on imagine un pipeline `allowed → should → query` exécuté à chaque fois. En réalité, `should()` et `query()` ne sont **jamais appelés dans le même appel** — ce sont deux traductions différentes de la même règle, choisies selon le contexte. D'après le code source du package (`Control::applies()` et `Control::applyQueryControl()`) :

**Autoriser un modèle précis** (`Gate::authorize('update', $seance)`, donc `ControlledPolicy`) :

```
pour chaque Perimeter :
    si allowed(user, method) :
        si le modèle n'existe pas (create/viewAny) → true, fini
        sinon → should(user, model) tranche
```

→ `allowed()` puis `should()`. `query()` n'est même pas regardé.

**Filtrer une liste** (`Seance::controlled()->get()`) :

```
pour chaque Perimeter :
    si allowed(user, 'view') :
        applique query(builder, user) sur la requête SQL
```

→ `allowed()` puis `query()`. `should()` n'est même pas regardé.

Le schéma correct :

```
allowed()  ←  toujours le premier filtre (le portail d'entrée du Perimeter)
   ├── record unique  → should()
   └── liste (query)  → query()
```

C'est pour ça qu'on écrit `should` et `query` **tous les deux** dans un Perimeter (même condition métier, formulée une fois en comparaison PHP sur un objet déjà chargé, une fois en `WHERE` SQL) — mais un seul des deux s'exécute par appel, jamais en séquence.

---

## 8. Ça existe ailleurs : Supabase (RLS) et JS (CASL)

Le problème que résout Controls/Perimeters n'est pas propre à Laravel — deux équivalents à connaître si tu recroises la question côté Supabase ou JS pur.

### Supabase → Row Level Security (RLS)

Le plus proche conceptuellement, même si ce n'est pas du code applicatif mais des règles **au niveau Postgres** :

```sql
-- Équivalent RLS de OwnPerimeter sur la table "seances"
create policy "coach modifie ses propres seances"
on seances for update
using (coach_id = auth.uid());

create policy "admin modifie tout"
on seances for update
using (exists (
  select 1 from user_roles
  where user_id = auth.uid() and role in ('admin', 'manager')
));
```

`using (...)` = le `query()` d'un Perimeter (filtre les lignes visibles/modifiables), appliqué **automatiquement** à chaque requête SQL par Postgres lui-même — comme `Model::controlled()`, sauf que c'est la base de données qui refuse la ligne, pas un `where()` ajouté côté Laravel. Plusieurs policies sur une table = plusieurs Perimeters sur un Control. Différence : RLS n'a pas de `should()` séparé — chaque policy est déjà scopée à une commande SQL (`select`/`insert`/`update`/`delete`), donc pas de distinction "modèle unique vs liste" à faire soi-même.

### JS/TS → CASL (`@casl/ability`)

La lib JS la plus proche en esprit — indépendante du framework (Node, NestJS, React côté front) :

```js
// Équivalent CASL de SeanceControl
import { AbilityBuilder, createMongoAbility } from '@casl/ability'

function defineAbilitiesFor(user) {
  const { can, build } = new AbilityBuilder(createMongoAbility)

  if (user.roles.includes('admin') || user.roles.includes('manager')) {
    can('update', 'Seance')                       // Global : tout
  } else {
    can('update', 'Seance', { coachId: user.id })  // Own : condition = should()
  }

  return build()
}

ability.can('update', seance) // équivalent de Gate::authorize('update', $seance)
```

Le 3ᵉ argument de `can(...)` (l'objet de conditions) joue le rôle de `should()` — CASL peut aussi transformer ces conditions en filtre Mongo/SQL (`rulesToQuery`), le pendant direct de `query()`/`controlled()`.

### Le point commun

Perimeters, RLS et CASL résolvent le même problème : **factoriser la règle "qui a accès à quoi" une seule fois**, pour qu'elle serve à la fois à autoriser une action ponctuelle et à filtrer une liste, au lieu de dupliquer la logique entre une Policy et un `where()` séparé. Un pattern qui revient partout dès qu'il y a des permissions par propriétaire — pas une invention propre à Laravel/lomkit.

---

## À retenir

- **Perimeter** = un périmètre d'accès réutilisable (`allowed`/`should`/`query`) ; **Control** = la liste des Perimeters valables pour un model (propriété **`$model`**) ; **`ControlledPolicy`** = la Policy qui délègue au Control (propriété **`$control`**, pas `$model`).
- `allowed()` reçoit un `$method` déjà mappé par `config('access-control.methods.*)` (`viewAny` → `view`) : sans le distinguer explicitement, `viewAny`/`view` tombent sous la même règle que `create`/`update`/`delete`.
- `view()` sur une Policy `ControlledPolicy` doit parfois rester surchargé à la main (signature `Model $user, Model $model`, pas de types plus précis) si l'ancien comportement était "ouvert à tous, peu importe le propriétaire".
- `controlled()` **ignore la Policy** — il consulte directement les Perimeters du Control. Une surcharge de `view()` sur la Policy n'a aucun effet sur `Model::controlled()->get()`.
- Le vrai gain sur une Policy à la main : `query()` sait **filtrer une liste** avec la même règle que `should()` autorise un modèle unique — mais vérifie que ce filtrage correspond vraiment au comportement souhaité du listing avant de le brancher (`enabled_by_default: false` dans `config/access-control.php`, sûr par défaut).
- Ça ne couvre que les 7 méthodes CRUD standards ; les actions métier custom (`cancel`, `manageParticipants`) restent à écrire à la main sur la Policy, en plus de `extends ControlledPolicy`.
- Le CRUD API lomkit du [Cours 14](14-lomkit-rest-api.md) n'a rien à changer : il appelle les mêmes noms d'ability (`viewAny`, `update`...), juste servis par le Control plutôt que par du code écrit à la main.
- Package en bêta au moment d'écrire ce cours — à garder en tête avant toute mise en prod.
- `allowed()` est toujours le premier filtre ; ensuite c'est `should()` (record unique) **ou** `query()` (liste), jamais les deux dans le même appel.
- Le pattern n'est pas propre à Laravel : Supabase (Row Level Security) et CASL (`@casl/ability`, JS/TS) résolvent le même problème — factoriser une règle d'accès pour qu'elle serve à la fois à autoriser et à filtrer une liste.

⬅️ Retour au [sommaire des cours](README.md)
