# Cours 6 — Authentification & permissions

> Objectif : savoir **qui protège quoi** dans une app Laravel. Deux notions à ne jamais mélanger : **authentification** (qui es-tu ?) et **autorisation** (as-tu le droit ?). Ton projet a 3 rôles gérés par `spatie/laravel-permission`. Tu connais déjà les guards NestJS et les policies Supabase — on ne fait que traduire les réflexes, une notion à la fois.

---

## 0. Le kit de survie (à lire en premier)

Trois idées suffisent à comprendre 90 % du chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **Authentification ≠ autorisation** | *Qui es-tu ?* (login/token) puis *as-tu le droit ?* (une fois connecté). Deux étapes séparées, deux mécaniques distinctes. |
| **On teste des PERMISSIONS, pas des rôles** | Dans le code métier, la question est « as-tu le droit `delete seances` ? », pas « es-tu `admin` ? ». Un rôle n'est qu'un paquet de permissions. |
| **Policy = autorisation par objet** | Pour « SES propres séances », une permission ne suffit pas : il faut comparer l'objet et l'utilisateur. C'est le job d'une **Policy**. |

Le reste, ce sont des outils autour de ces trois idées.

---

## 1. Authentification vs autorisation

Deux questions différentes, posées dans l'ordre :

- **Authentification** — prouver son identité (login/mot de passe, ou token JWT en Partie II). *« Qui es-tu ? »*
- **Autorisation** — vérifier les droits une fois connecté (« ce coach peut-il supprimer CETTE séance ? »). *« As-tu le droit ? »*

> 💡 En NestJS, c'est exactement la séparation `AuthGuard` (qui décode le token et pose le user) vs `RolesGuard` / `CaslGuard` (qui décide si le user passe). L'un identifie, l'autre autorise.

L'utilisateur courant est accessible partout :

```php
auth()->user();     // le User connecté (ou null)
$request->user();   // idem, depuis une requête
auth()->id();       // son id
```

> 💡 `auth()->user()`, c'est le user que NestJS injecte dans `req.user` après le guard, ou le `auth.uid()` de Supabase : l'identité déjà résolue, dispo sans la reconstruire.

---

## 2. Les rôles du projet

Trois rôles, avec des droits croissants :

| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| `admin` | toutes | toutes | toutes |
| `coach` | les siennes | les siennes | non |
| `collaborator` | non | non | non |

Retiens la ligne `coach` : « les siennes » — c'est elle qui va nous forcer à sortir les Policies en section 5. Une permission seule ne sait pas dire « les siennes ».

---

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

> 💡 `use HasRoles;` est un **trait** (cf. Cours 1) : il colle sur le model toutes les méthodes ci-dessous (`assignRole`, `can`, etc.) sans que tu les écrives.

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

> 💡 Le middleware `role:` / `permission:` est **exactement** un guard NestJS posé sur une route : il barre l'entrée avant même que le controller s'exécute. Ici c'est déclaratif, dans la définition de route, au lieu d'un `@UseGuards()` sur le handler.

---

## 4. 🔴 Convention XEFI — raisonner en permissions, pas en rôles

Dans le **code métier**, on teste des **permissions**, jamais des rôles :

```php
// ✅ BON — souple : n'importe quel rôle avec cette permission passe
if ($user->can('delete seances')) { … }

// ❌ ÉVITER dans la logique métier — rigide
if ($user->hasRole('admin')) { … }
```

Les rôles ne sont qu'un **regroupement de permissions**. Tester la permission garde les règles souples (si demain un « manager » peut supprimer, tu ajoutes la permission au rôle, sans toucher le code).

> 💡 Le réflexe est le même qu'avec des **feature flags** ou des scopes OAuth : tu vérifies une capacité (`delete seances`), pas une étiquette de personne (`admin`). Le code décrit *ce qui est permis*, pas *qui est qui*.

---

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

> 💡 C'est le pendant Laravel d'une **RLS policy Supabase** (`auth.uid() = coach_id`) ou d'une règle CASL dans NestJS : la décision dépend de la *ligne* visée, pas seulement du profil de l'utilisateur. Une permission dit « tu peux modifier des séances » ; la Policy dit « … mais celle-ci précisément ? ».

---

## À retenir

- Auth = identité ; autorisation = droits. `auth()->user()` partout.
- Rôles `admin` / `coach` / `collaborator` via `spatie/laravel-permission` (trait `HasRoles`).
- **Convention XEFI** : dans le code, tester `can('permission')`, pas `hasRole('role')`.
- **Policy** pour l'autorisation par objet (« ses propres séances »), appelée via `authorize()`.

## ⚠️ Les pièges qui piquent au début

1. **Confondre authentification et autorisation.** Un user connecté (`auth()->user()` renvoie quelqu'un) n'a pas pour autant le droit de tout faire. Être identifié, ce n'est pas être autorisé — ce sont deux vérifications séparées.
2. **Tester `hasRole('admin')` dans le métier au lieu de `can(...)`.** Ça marche… jusqu'au jour où un nouveau rôle doit faire la même chose et où tu dois rouvrir tous les `if`. Teste la permission (cf. la 🔴 convention XEFI section 4).
3. **Croire qu'une permission suffit pour « ses propres séances ».** `can('update seances')` autorise à modifier *des* séances, pas à distinguer *lesquelles*. Dès qu'il faut comparer l'objet et l'utilisateur (`coach_id === $user->id`), il te faut une **Policy**.
4. **Oublier d'appeler l'autorisation.** Une Policy écrite mais jamais déclenchée ne protège rien : sans `$this->authorize('update', $seance)` (ou le `can` dans la Form Request), le controller s'exécute quand même.

➡️ Suite : [Cours 7 — Events, Listeners & Notifications](07-events-listeners-notifications.md)
