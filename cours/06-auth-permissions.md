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

Quatre rôles :

| Rôle | Créer | Modifier | Annuler | Supprimer | Gérer participants | S'inscrire (soi) |
|---|---|---|---|---|---|---|
| `admin` | toutes | toutes | toutes | toutes | toutes | ✅ |
| `manager` (admin des coachs) | non | toutes | toutes | toutes | toutes | ✅ |
| `coach` | ✅ | les siennes | les siennes | les siennes | les siennes | ✅ |
| `collaborator` | non | non | non | non | non | ✅ |

Deux choses à retenir :

- La ligne `coach` : « les siennes » — c'est elle qui va nous forcer à sortir les Policies en section 5. Une permission seule ne sait pas dire « les siennes ».
- La colonne **S'inscrire (soi)** est à `✅` pour **tout le monde**, et elle ne passe **pas** par les permissions ci-dessus : s'inscrire à une séance n'est **pas** la modifier. C'est une action séparée (route + controller dédiés), sinon un collaborateur aurait besoin du droit de modifier la séance juste pour s'y inscrire. À ne jamais confondre.

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

> 🔴 **Piège `guard_name`.** Un rôle/permission Spatie est rattaché à un **guard** (`web` par défaut). Si `assignRole()` lève une erreur du type « There is no role named … for guard … », le rôle existe mais **pas pour le bon guard**. Trois choses à vérifier dans l'ordre :
> 1. Le rôle a été créé pour le **même guard** que celui du `User` (par défaut `web` des deux côtés → OK).
> 2. Le `User` model force bien son guard si besoin : `protected $guard_name = 'web';`.
> 3. `config/auth.php` — si le projet définit **plusieurs guards** (`web` + `api` en Partie II), Spatie peut chercher le mauvais. C'est le premier fichier à ouvrir si ça te retombe dessus sur un autre projet.

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

## Questions qui reviennent

**« Breeze, Fortify ou à la main ? »**

Trois façons de brancher l'authentification, du plus « clé en main » au plus artisanal :

- **Breeze** : génère la logique d'auth **ET** les vues (pages login/register). Le code atterrit dans ton projet, visible et modifiable. Analogie : un `create-next-app` qui te livre déjà les pages d'auth écrites.
- **Fortify** : **headless** — la logique d'auth **sans** les vues. Tu fournis ton propre front (Blade, Vue, Inertia, peu importe). Analogie : un module d'auth NestJS sans templates, tu câbles la couche présentation toi-même.
- **À la main** : tu écris toi-même controllers, routes et vues. Le plus formateur, mais le plus long.

> 💡 Reco pour débuter **en comprenant** ce qui se passe : **Breeze** (tu lis du vrai code, tu vois comment login/logout sont réellement implémentés). Passe à **Fortify** quand ton front est découplé (SPA, mobile) et que tu veux garder la main sur les vues.

**« Session ou JWT ? »**

Ça dépend de la partie du cours — et surtout du type de front :

- **Partie I (web)** : **session + cookie**, guard `web`. C'est le mode « application web classique » et c'est ce qu'on utilise ici pour apprendre. Le serveur garde l'état de connexion.
- **Partie II (API)** : **JWT stateless**, guard `api`, pour un front séparé qui parle à l'API. Rien n'est stocké côté serveur : le token porte l'identité. On y arrive au **Cours 8**.

> 💡 Même dualité qu'ailleurs : une app monolithique avec ses pages rendues côté serveur → session. Un front découplé (Nuxt, mobile) qui tape une API → token. Ce n'est pas « l'un est mieux que l'autre », c'est deux contextes différents.

---

## 🔴 En vrai chez StackTim

Tout ce qu'on vient de voir sur les **Policies** reste la version **pédagogique** : on écrit la règle métier à la main pour **comprendre** ce qu'est une autorisation par objet. En production, chez StackTim, on ne fait **pas** ça à la main comme en section 5. On s'appuie sur le package **`lomkit/laravel-access-control`**, posé par-dessus spatie.

L'idée : plutôt que d'écrire des `if` dans chaque Policy, on déclare **où** un utilisateur a le droit d'agir (son « périmètre »), et le package s'occupe du reste — y compris de **filtrer les résultats** des requêtes.

- **Un `Control` par modèle** (ex. `UserControl`) déclare des **Perimeters**. Les plus courants :
  - `GlobalPerimeter` — a le droit partout (ex. un admin) ;
  - `OwnedBusinessUnitPerimeter` — seulement dans sa Business Unit ;
  - `OwnPerimeter` — seulement ses propres ressources.
- **Chaque Perimeter combine trois choses** :
  - un **`allowed()`** qui vérifie une **permission spatie** nommée selon la convention `{method}_{scope}_{resource}` (ex. `view_users`, `create_own_users`) ;
  - un **`should()`** — le test d'appartenance (« cet objet tombe-t-il bien dans ce périmètre ? ») ;
  - un **`query()`** qui **filtre aussi les résultats** d'une requête selon le périmètre (l'utilisateur ne voit remonter que ce qu'il a le droit de voir).
- **La Policy devient ultra-mince** : elle étend `ControlledPolicy` et pointe simplement vers son Control.

```php
// La Policy ne contient plus de logique métier : elle délègue au Control
class UserPolicy extends ControlledPolicy
{
    protected string $control = UserControl::class;
}
```

- **Le model porte le trait `HasControl`** (comme il porte `HasRoles` pour spatie).

> 💡 Le partage des rôles : **spatie** fournit les **permissions atomiques** (`view_users`, `create_own_users`…), **lomkit access-control** fournit la **logique de périmètre + le filtrage de requêtes**, et les **Policies délèguent** au Control au lieu de coder la règle en dur.

> ⚠️ La Policy « à la main » de la section 5 n'est donc pas ce que tu écriras en prod — c'est l'étape pour **COMPRENDRE** ce que `lomkit/laravel-access-control` automatise. Un `$seance->coach_id === $user->id` écrit à la main, c'est exactement le genre de règle qu'un `OwnPerimeter` encapsule et applique partout, filtrage des listes compris.

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
