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
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, string $method) => $user->hasRole(['admin', 'manager']))
                ->should(fn (User $user, Model $model) => true)
                ->query(fn (Builder $query, User $user) => $query),

            OwnPerimeter::new()
                ->allowed(fn (User $user, string $method) => $user->can("{$method} seances"))
                ->should(fn (User $user, Model $model) => $model->coach_id === $user->id)
                ->query(fn (Builder $query, User $user) => $query->where('coach_id', $user->id)),
        ];
    }
}
```

> 💡 On retrouve exactement la logique de `ownsOrManages()` (`hasRole(['admin', 'manager']) || $seance->coach_id === $user->id`) — mais éclatée en deux Perimeters au lieu d'un seul booléen. C'est ce découpage qui permet de la réutiliser aussi pour filtrer une liste (`query()`), ce qu'un simple `if` dans une Policy ne sait pas faire.

**Le modèle** : ajoute `HasControl`.

```php
// app/Models/Seance.php
use Lomkit\Access\Controls\HasControl;

class Seance extends Model implements HasMedia
{
    use HasFactory, HasControl, InteractsWithMedia, SoftDeletes;
}
```

**La Policy** devient une coquille vide qui pointe vers le Control — plus de `viewAny`/`view`/`update`/`delete` à écrire à la main :

```php
// app/Policies/SeancePolicy.php
use Lomkit\Access\Policies\ControlledPolicy;

class SeancePolicy extends ControlledPolicy
{
    protected string $model = Seance::class;
}
```

`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` sont couvertes automatiquement par ce mécanisme — cf. cours 14 : c'est exactement les méthodes que lomkit/laravel-rest-api appelle déjà via `Gate::inspect(...)`, donc **le CRUD API du cours 14 continue de marcher sans y toucher**.

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

---

## 5. Ce que ça remplace, ce que ça ne remplace pas

- **Remplace** : les méthodes CRUD standards des Policies (`viewAny`/`view`/`create`/`update`/`delete`) et les `where()` de filtrage par rôle écrits à la main dans les controllers/services.
- **Ne remplace pas** : les actions métier custom (`cancel`, `manageParticipants` du cours 12/14) — ce sont des ability names hors du contrat `ControlledPolicy`, donc `SeancePolicy` garde ces deux méthodes écrites à la main, en plus de `extends ControlledPolicy` :

```php
class SeancePolicy extends ControlledPolicy
{
    protected string $model = Seance::class;

    public function cancel(User $user, Seance $seance): bool
    {
        return $user->can('cancel seances') && ($user->hasRole(['admin', 'manager']) || $seance->coach_id === $user->id);
    }

    public function manageParticipants(User $user, Seance $seance): bool
    {
        return $user->can('manage participants') && ($user->hasRole(['admin', 'manager']) || $seance->coach_id === $user->id);
    }
}
```

> 💡 Les policies `UserPolicy`/`PlacePolicy` (cours 14, nécessaires pour les relations `coach`/`place` en API) peuvent rester de simples Policies classiques — Controls/Perimeters n'a de sens que là où la règle "qui a accès à quoi" varie par rôle, ce qui n'est pas le cas ici (`viewAny` = `true` pour tout le monde).

---

## À retenir

- **Perimeter** = un périmètre d'accès réutilisable (`allowed`/`should`/`query`) ; **Control** = la liste des Perimeters valables pour un model ; **`ControlledPolicy`** = la Policy qui délègue au Control.
- Le vrai gain sur une Policy à la main : `query()` sait **filtrer une liste** avec la même règle que `should()` autorise un modèle unique — plus besoin de garder deux logiques synchronisées.
- Ça ne couvre que les 7 méthodes CRUD standards ; les actions métier custom (`cancel`, `manageParticipants`) restent à écrire à la main sur la Policy, en plus de `extends ControlledPolicy`.
- Le CRUD API lomkit du [Cours 14](14-lomkit-rest-api.md) n'a rien à changer : il appelle les mêmes noms d'ability (`viewAny`, `update`...), juste servis par le Control plutôt que par du code écrit à la main.
- Package en bêta au moment d'écrire ce cours — à garder en tête avant toute mise en prod.

⬅️ Retour au [sommaire des cours](README.md)
