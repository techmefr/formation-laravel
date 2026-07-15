# Cours 8 — API REST, JWT & lomkit (Partie II)

> La Partie II transforme l'appli web en **API REST** sécurisée par JWT. Mêmes règles métier (rôles, permissions, notifications), mais **sans sessions** : chaque requête porte un token. Tu connais déjà ce modèle côté front (Supabase, JWT en header) — on ne fait que le regarder depuis le serveur, une notion à la fois.

---

## 0. Le kit de survie (à lire en premier)

Trois idées suffisent à comprendre 90 % du chapitre :

| Idée | Ce que ça veut dire pour toi |
|---|---|
| **L'API est stateless** | Plus de session côté serveur. Chaque requête porte son **token JWT** dans l'en-tête `Authorization`. Le serveur le vérifie et l'oublie aussitôt. |
| **Une Resource contrôle la forme du JSON** | Tu ne renvoies jamais les colonnes brutes du model. Une **API Resource** décide exactement ce qui sort — comme un DTO / serializer. |
| **lomkit industrialise le CRUD, pas la sécurité** | lomkit génère les endpoints `search` / `mutate` à partir d'une classe. Mais les **permissions/Policies (cours 6) restent obligatoires** : tu dois toujours autoriser. |

Le reste, ce sont des détails pratiques autour de ces trois idées.

---

## 1. Stateful vs stateless

- **Partie I (web)** : session + cookie. Le serveur se souvient que tu es connecté.
- **Partie II (API)** : *stateless*. Aucune session ; chaque requête envoie un **token** dans l'en-tête. Le serveur le vérifie et l'oublie aussitôt.

> 💡 C'est exactement le modèle que tu connais côté front avec **Supabase** : pas de cookie de session, tu balades un JWT dans le header `Authorization` à chaque appel. Ici tu es de l'autre côté du fil, celui qui reçoit et vérifie le token.

---

## 2. JWT avec tymon/jwt-auth

Un **JWT** (JSON Web Token) est un jeton **signé** que le client renvoie à chaque requête :

```
Authorization: Bearer <token>
```

Le serveur vérifie la signature sans stocker de session. Le token encode l'identité (et une expiration).

> 💡 Rien de nouveau pour toi ici : c'est le même Bearer token que tu envoies depuis le front. La seule différence, c'est que maintenant c'est **ton** serveur qui le signe (à la connexion) et le vérifie (à chaque requête protégée).

```php
// app/Http/Controllers/AuthController.php
public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (! $token = auth('api')->attempt($credentials)) {
        return response()->json(['error' => 'Identifiants invalides'], 401);
    }

    return response()->json([
        'access_token' => $token,
        'token_type'   => 'bearer',
        'expires_in'   => auth('api')->factory()->getTTL() * 60,
    ]);
}
```

Protéger les routes avec le guard `auth:api` :

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/seances/{seance}/inscription', [InscriptionController::class, 'store']);
    // … tous les endpoints protégés
});
```

> 💡 Le guard `auth:api` joue exactement le rôle d'un **guard NestJS** (`@UseGuards(JwtAuthGuard)`) : il s'intercale avant le controller, lit le token, refuse la requête si le token manque ou est invalide. Les routes hors du `group()` (comme `/login`) restent publiques.

Le model `User` implémente alors l'interface `JWTSubject` (deux méthodes : `getJWTIdentifier()`, `getJWTCustomClaims()`).

> 💡 `implements JWTSubject`, c'est le même mécanisme que le `implements` TS vu au cours 1 : le model signe un **contrat** qui dit à la lib « voici comment m'identifier dans le token ».

---

## 3. Les API Resources — formater le JSON

Pour ne pas exposer toutes les colonnes brutes, on transforme un model en JSON via une **Resource** :

```bash
sail artisan make:resource SeanceResource
```

```php
public function toArray($request): array
{
    return [
        'id'           => $this->id,
        'name'         => $this->name,
        'startedAt'    => $this->started_at,
        'coach'        => new UserResource($this->whenLoaded('coach')),
        'placesLibres' => $this->max_participants - $this->participants_count,
    ];
}
```

> 💡 C'est l'équivalent d'un **DTO / serializer** : tu contrôles précisément la forme envoyée au front. Comme quand tu mappes une entité vers un DTO de sortie en NestJS — la table peut avoir 30 colonnes, le JSON n'en expose que celles que tu listes ici, avec les noms que tu veux (`started_at` → `startedAt`).

---

## 4. Industrialisation avec lomkit/laravel-rest-api

Écrire à la main chaque endpoint CRUD (+ filtres, tri, pagination, relations) est répétitif. **lomkit/laravel-rest-api** génère une API riche à partir d'une classe **Resource** (au sens lomkit). C'est le standard StackTim.

Idée clé : le client décrit sa requête en **JSON** via deux endpoints génériques :
- **`search`** — lire : filtres, scopes, tri, includes de relations, pagination.
- **`mutate`** — écrire : créer/mettre à jour, y compris des relations imbriquées, en masse.

```jsonc
// POST /api/seances/search
{
  "search": {
    "filters": [{ "field": "coach_id", "operator": "=", "value": 3 }],
    "includes": [{ "relation": "participants" }],
    "sorts": [{ "field": "started_at", "direction": "asc" }]
  }
}
```

Côté serveur, tu **déclares ce qui est autorisé** dans la Resource lomkit : champs filtrables, relations exposées, permissions requises. Tu configures cette couche plutôt que d'écrire 20 controllers.

```php
// app/Rest/Resources/SeanceResource.php (lomkit)
class SeanceResource extends Resource
{
    public static $model = Seance::class;

    public function fields(RestRequest $request): array { return ['id', 'name', 'started_at']; }
    public function filters(RestRequest $request): array { return [/* champs filtrables */]; }
    public function relations(RestRequest $request): array { return [/* relations exposées */]; }
}
```

> 💡 Le réflexe à garder : tu passes de « j'écris chaque endpoint » à « je **déclare** ce que le client a le droit de demander ». C'est de la config, pas du code impératif. Ce que tu ne listes pas dans `fields()` / `filters()` / `relations()` n'existe tout simplement pas pour le client.

> ⚠️ Le contrôle d'accès (permissions du cours 6, Policies) reste **obligatoire** : lomkit ne dispense pas d'autoriser. Tu branches tes permissions dans la Resource.

---

## 5. Tester l'API

`sail artisan route:list --path=api` pour voir les endpoints, puis un client HTTP (Postman/Insomnia/Bruno, ou `curl`). Flux type :
1. `POST /api/login` → récupérer le token.
2. Rejouer les requêtes avec `Authorization: Bearer <token>`.

> 💡 C'est le même aller-retour que tu fais déjà à la main dans Postman côté front : un premier appel pour obtenir le token, puis tu le colles dans l'onglet Authorization pour toutes les requêtes suivantes.

---

## En vrai chez StackTim (platform-api)

Le vrai projet StackTim (`platform-api`) applique exactement ce modèle JWT, avec quelques choix concrets qui valent le détour. Voici comment c'est câblé en prod.

### Le guard `api` en driver `jwt` par défaut

Dans `config/auth.php`, le guard **par défaut de l'API est `api`, en driver `jwt`**. Le guard `web` (session) existe toujours, mais ce n'est **pas** le défaut côté API. Le provider `users` pointe sur le model `User`.

```php
// config/auth.php (idée)
'defaults' => ['guard' => 'api'],
'guards' => [
    'api' => ['driver' => 'jwt', 'provider' => 'users'],
    'web' => ['driver' => 'session', 'provider' => 'users'],
],
'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
],
```

> 💡 C'est ce qui te permet d'écrire `auth('api')->...` (ou même `auth()->...` puisque `api` est le défaut) partout dans l'API sans repréciser le guard à chaque fois.

### Le model `User` signe le contrat `JWTSubject`

Comme vu en section 2, `User implements JWTSubject`. Concrètement, les deux méthodes sont minimalistes :

```php
// app/Models/User.php
public function getJWTIdentifier(): mixed
{
    return $this->getKey();       // l'id qui sera encodé dans le token
}

public function getJWTCustomClaims(): array
{
    return [];                     // aucun claim custom
}
```

### Pas de login email/mot de passe en prod : c'est Azure OAuth

Le point qui surprend quand on vient de la Partie I : **il n'y a pas de `login` email/mot de passe classique en prod**. L'entrée réelle, c'est **Azure OAuth** via Socialite en mode `stateless()` (logique : une API stateless ne garde pas de session OAuth).

Après le callback Azure, le serveur génère le token depuis le user et le dépose dans un **cookie `access-token`** (durée ~30 min, `sameSite=Lax`) :

```php
// callback Azure (idée)
$user = Socialite::driver('azure')->stateless()->user();
// ... on retrouve/crée le User local ...
$token = JWTAuth::fromUser($user);

$cookie = cookie('access-token', $token, 30, sameSite: 'Lax', httpOnly: false);
return redirect($frontUrl)->withCookie($cookie);
```

> ⚠️ Détail clé : le cookie est **`httpOnly = false`**. Ce n'est pas un oubli — c'est **volontaire** pour que le **front JS puisse lire le cookie** et renvoyer lui-même le header `Authorization: Bearer <token>` à chaque appel. Le serveur ne lit **jamais** le token depuis le cookie.

> 💡 Côté front tu retrouves un réflexe connu (Supabase/NestJS) : tu récupères le JWT, tu le mets dans le header `Authorization` de chaque requête. La seule bizarrerie, c'est que le token transite d'abord par un cookie lisible en JS au lieu d'un corps de réponse JSON.

### Et un login email/mot de passe, alors ?

Pour un login email/mot de passe équivalent (ce qu'on fera en formation, faute d'Azure), l'idiome serait exactement celui de la section 2, déposé dans le même cookie :

```php
$token = auth('api')->attempt($credentials);
// puis même dépôt : cookie('access-token', $token, 30, sameSite: 'Lax', httpOnly: false)
```

### Protéger les routes : le middleware `jwt.auth`

En prod, les groupes de routes sont protégés par le middleware **`jwt.auth`** — l'alias natif fourni par `tymon/jwt-auth`. Il lit le token depuis le header `Authorization: Bearer` (pas depuis le cookie).

```php
// routes/api.php (idée)
Route::middleware('jwt.auth')->group(function () {
    // … endpoints protégés
});
```

> ⚠️ Il n'y a **aucun** middleware serveur qui recopie le cookie dans le header `Authorization`. C'est le **front** qui s'en charge (il lit le cookie `access-token` et pose le header lui-même). Le serveur, lui, ne regarde que le header.

### Le trio `refresh` / `logout` / `me`

```php
public function refresh() { $token = auth()->refresh(true, true); /* re-déposé en cookie access-token */ }
public function logout()  { auth()->logout(); Cookie::forget('access-token'); }
public function me()      { return auth()->user()->load(/* relations utiles */); }
```

### L'autorisation : lomkit access-control

Une fois le user authentifié (JWT), **qui a le droit de faire quoi** passe par `lomkit/laravel-access-control` (les **Controls** et **Perimeters**) — c'est ce qu'on a vu au Cours 6, section « En vrai chez StackTim ». Le partage des rôles :
- **`lomkit/laravel-rest-api`** génère les endpoints (`search` / `mutate`, cf. section 4).
- **`lomkit/laravel-access-control`** filtre et autorise (qui voit quoi, qui peut muter quoi).

> 💡 Retiens la séparation nette : **JWT = authentification** (qui es-tu), **access-control = autorisation** (qu'as-tu le droit de faire). Deux couches distinctes, comme `JwtAuthGuard` puis un `RolesGuard`/`CASL` côté NestJS.

---

> **Mise en perspective** : la **Partie II du projet formation** reproduit exactement ce mécanisme — **JWT + guard `api` + lomkit** — en remplaçant simplement **Azure OAuth par un login email/mot de passe**. Tu verras donc le même câblage (token en cookie lisible, header `Authorization` posé côté front, routes protégées par `jwt.auth`, autorisation par lomkit), mais avec une porte d'entrée que tu peux tester en local sans compte Azure.

---

## À retenir

- API = **stateless**, token JWT dans `Authorization: Bearer` (via `tymon/jwt-auth`, guard `auth:api`).
- **API Resource** = contrôle de la forme du JSON (comme un DTO/serializer).
- **lomkit** industrialise via `search` (lecture) / `mutate` (écriture) décrits en JSON ; tu déclares le permis côté serveur.
- Les **permissions/Policies** restent obligatoires même avec lomkit.

## ⚠️ Les pièges qui piquent au début

1. **Oublier le header `Authorization: Bearer <token>`** sur une route protégée → `401`. Ce n'est pas un bug du serveur : le guard `auth:api` fait son travail. Premier réflexe quand tu prends un 401 : vérifie que le token est bien envoyé (et pas expiré).
2. **Croire que lomkit t'autorise à ne plus autoriser** : non. `search` / `mutate` génèrent les endpoints, mais les **Policies / permissions du cours 6 restent obligatoires**. Sans elles, tu exposes un CRUD ouvert à tous.
3. **Renvoyer le model brut au lieu de passer par une Resource** : tu exposes alors toutes les colonnes (y compris celles que le front ne devrait jamais voir). Passe toujours par une API Resource pour choisir ce qui sort.
4. **Attendre qu'un champ non déclaré soit filtrable/exposé** : côté lomkit, ce qui n'est pas listé dans `fields()` / `filters()` / `relations()` n'existe pas pour le client. Si un filtre « ne marche pas », commence par vérifier qu'il est bien déclaré.

⬅️ Retour au [sommaire des cours](README.md) · Prochaine étape : **monter le vrai projet**.
