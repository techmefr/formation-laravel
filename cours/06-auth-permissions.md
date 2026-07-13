# Cours 6 — Authentification & permissions

> Deux notions distinctes : **authentification** (qui es-tu ?) et **autorisation** (as-tu le droit ?). Ton projet a 3 rôles gérés par `spatie/laravel-permission`.

## 1. Authentification vs autorisation

- **Authentification** — prouver son identité (login/mot de passe, ou token JWT en Partie II).
- **Autorisation** — vérifier les droits une fois connecté (« ce coach peut-il supprimer CETTE séance ? »).

L'utilisateur courant est accessible partout :

```php
auth()->user();     // le User connecté (ou null)
$request->user();   // idem, depuis une requête
auth()->id();       // son id
```

## 2. Les rôles du projet

| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| `admin` | toutes | toutes | toutes |
| `coach` | les siennes | les siennes | non |
| `collaborator` | non | non | non |

## 3. spatie/laravel-permission

Le package impose une distinction **rôles** vs **permissions**. On y branche le model `User` avec un trait :

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Puis :

```php
// Attribution
$user->assignRole('coach');
$user->givePermissionTo('create seances');

// Vérifications
$user->hasRole('admin');          // true/false
$user->can('delete seances');     // basé sur les permissions
```

Protéger une route par middleware :

```php
Route::delete('/seances/{seance}', [SeanceController::class, 'destroy'])
    ->middleware('role:admin');            // ou 'permission:delete seances'
```

## 4. 🔴 Convention XEFI — raisonner en permissions, pas en rôles

Dans le **code métier**, on teste des **permissions**, jamais des rôles :

```php
// ✅ BON — souple : n'importe quel rôle avec cette permission passe
if ($user->can('delete seances')) { … }

// ❌ ÉVITER dans la logique métier — rigide
if ($user->hasRole('admin')) { … }
```

Les rôles ne sont qu'un **regroupement de permissions**. Tester la permission garde les règles souples (si demain un « manager » peut supprimer, tu ajoutes la permission au rôle, sans toucher le code).

## 5. Les Policies — l'autorisation « par objet »

Pour « un coach ne peut modifier QUE ses propres séances », une simple permission ne suffit pas : il faut comparer l'objet et l'utilisateur. C'est le rôle d'une **Policy**.

```bash
sail artisan make:policy SeancePolicy --model=Seance
```

```php
// app/Policies/SeancePolicy.php
public function update(User $user, Seance $seance): bool
{
    // admin peut tout ; un coach seulement SA séance
    return $user->can('update seances')
        && ($user->hasRole('admin') || $seance->coach_id === $user->id);
}

public function delete(User $user, Seance $seance): bool
{
    return $user->hasRole('admin');   // seul l'admin supprime
}
```

On l'appelle depuis le controller ou la Form Request :

```php
$this->authorize('update', $seance);   // lève un 403 si refusé
// ou dans une Form Request : return $this->user()->can('update', $this->route('seance'));
```

> La Policy combine la **permission** (le droit générique) et la **règle métier** (propriété de l'objet). C'est le bon endroit pour « les siennes seulement ».

---

## À retenir

- Auth = identité ; autorisation = droits. `auth()->user()` partout.
- Rôles `admin` / `coach` / `collaborator` via `spatie/laravel-permission` (trait `HasRoles`).
- **Convention XEFI** : dans le code, tester `can('permission')`, pas `hasRole('role')`.
- **Policy** pour l'autorisation par objet (« ses propres séances »), appelée via `authorize()`.

➡️ Suite : [Cours 7 — Events, Listeners & Notifications](07-events-listeners-notifications.md)
