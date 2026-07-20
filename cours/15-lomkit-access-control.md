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

Un Control peut avoir plusieurs Perimeters (admin = `Global`, coach = `Own`) : le premier qui répond `allowed() && should()` gagne.

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

## À retenir

- **Perimeter** = un périmètre d'accès réutilisable (`allowed`/`should`/`query`) ; **Control** = la liste des Perimeters valables pour un model (propriété **`$model`**) ; **`ControlledPolicy`** = la Policy qui délègue au Control (propriété **`$control`**, pas `$model`).
- `allowed()` reçoit un `$method` déjà mappé par `config('access-control.methods.*)` (`viewAny` → `view`) : sans le distinguer explicitement, `viewAny`/`view` tombent sous la même règle que `create`/`update`/`delete`.
- `view()` sur une Policy `ControlledPolicy` doit parfois rester surchargé à la main (signature `Model $user, Model $model`, pas de types plus précis) si l'ancien comportement était "ouvert à tous, peu importe le propriétaire".
- `controlled()` **ignore la Policy** — il consulte directement les Perimeters du Control. Une surcharge de `view()` sur la Policy n'a aucun effet sur `Model::controlled()->get()`.
- Le vrai gain sur une Policy à la main : `query()` sait **filtrer une liste** avec la même règle que `should()` autorise un modèle unique — mais vérifie que ce filtrage correspond vraiment au comportement souhaité du listing avant de le brancher (`enabled_by_default: false` dans `config/access-control.php`, sûr par défaut).
- Ça ne couvre que les 7 méthodes CRUD standards ; les actions métier custom (`cancel`, `manageParticipants`) restent à écrire à la main sur la Policy, en plus de `extends ControlledPolicy`.
- Le CRUD API lomkit du [Cours 14](14-lomkit-rest-api.md) n'a rien à changer : il appelle les mêmes noms d'ability (`viewAny`, `update`...), juste servis par le Control plutôt que par du code écrit à la main.
- Package en bêta au moment d'écrire ce cours — à garder en tête avant toute mise en prod.

⬅️ Retour au [sommaire des cours](README.md)
