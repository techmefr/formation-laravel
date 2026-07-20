# Cours 13 — JWT à la main (pas à pas, Partie II API)

> But : brancher l'auth JWT (guard `api`) sur le projet, en réutilisant ce que tu connais déjà côté théorie ([Cours 8](08-api-rest-jwt-lomkit.md)) et côté patron de code ([Cours 11](11-auth-a-la-main.md), auth web). Tu as déjà l'auth session (Partie I) ; ici on ajoute une **deuxième porte d'entrée**, stateless, à côté — on ne touche pas à `routes/web.php`.

---

## 0. La question à trancher avant de coder : dans quel ordre ?

Le réflexe naturel serait : *« on écrit `api.php`, on refait toutes les routes du web en JSON, et on branche JWT à la fin »*. C'est l'ordre inverse de ce qu'il faut faire, pour une raison simple :

> Une route déclarée sans middleware d'auth est **ouverte à tout le monde** dès qu'elle existe. Si tu écris le CRUD séances en API avant d'avoir JWT, tu as une fenêtre — même courte — où l'endpoint est public.

L'ordre correct, et celui qu'on suit ici :

1. **JWT d'abord** : le guard `api`, le login, une route protégée bidon (`/me`) pour valider le mécanisme.
2. **Les endpoints métier ensuite** (CRUD séances, cours 14+), qui naissent **déjà** dans un groupe `middleware('auth:api')`.

Pas de « toutes les routes web réécrites » non plus : l'API n'est pas une traduction 1:1 des routes web. Tu ne re-fais pas `/register`, `/forgot-password`, etc. en JSON par défaut — tu exposes ce dont un client API a réellement besoin (ici : se connecter, se voir soi-même, rafraîchir son token, se déconnecter). Le reste (CRUD séances, inscriptions) viendra au fil des cours suivants, avec lomkit.

---

## 1. La carte : où vivent les fichiers JWT

| Rôle | Emplacement |
|---|---|
| Le guard `api` | `config/auth.php` |
| La config du package JWT | `config/jwt.php` (publiée depuis `tymon/jwt-auth`) |
| Le contrat JWT sur le model | `app/Models/User.php` (`implements JWTSubject`) |
| La logique d'auth API | `app/Services/AuthService.php` (mêmes méthodes que l'auth web, à côté) |
| Le controller API | `app/Http/Controllers/Api/AuthController.php` |
| Les routes API | `routes/api.php` |
| Le branchement des routes API | `bootstrap/app.php` (`withRouting(api: ...)`) |

> 💡 Remarque la séparation : `Api/AuthController` (JSON) est un controller **différent** de `Auth/AuthenticatedSessionController` (redirections + vues Blade). Même besoin métier (se connecter), deux formats de réponse, deux controllers. C'est la même logique que d'avoir un controller REST et un controller GraphQL séparés côté NestJS pour la même ressource.

---

## 2. Étape 1 — le package et le secret

`tymon/jwt-auth` était déjà dans `composer.json`. Deux commandes à lancer une seule fois par projet :

```bash
sail artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
sail artisan jwt:secret
```

- La première copie `config/jwt.php` (durée de vie du token, algo de signature…).
- La seconde génère `JWT_SECRET` dans `.env` — c'est la clé qui **signe** les tokens. Si tu la perds ou la changes, tous les tokens émis avant deviennent invalides (comportement voulu : ça permet de « tout déconnecter » en cas de fuite).

⚠️ `JWT_SECRET` reste dans `.env`, jamais commité (comme `APP_KEY`).

---

## 3. Étape 2 — le guard `api`

Dans `config/auth.php`, à côté du guard `web` existant :

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

Même provider (`users` → model `User`) que le guard web : c'est la **même table**, juste une autre façon de prouver qui tu es (token au lieu de session). On garde `defaults.guard = web` — l'app web continue de tourner sur session ; l'API précise `guard('api')` explicitement partout. (En prod StackTim, `api` est le guard par défaut de l'appli — mais ça suppose que *toute* l'appli soit API. Ici on cohabite avec le web de la Partie I, donc on ne bascule pas le défaut.)

---

## 4. Étape 3 — le model signe le contrat `JWTSubject`

```php
// app/Models/User.php
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    // ...

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
```

> 💡 `implements JWTSubject`, c'est un `interface` TS classique (cours 1) : la lib jwt-auth ne connaît pas ton model, mais elle sait que « tout ce qui implémente `JWTSubject` sait me dire son identifiant ». `getJWTIdentifier()` = l'id encodé dans le token. `getJWTCustomClaims()` = des infos additionnelles dans le payload (vide ici, volontairement — pas besoin de claims custom pour l'instant).

---

## 5. Étape 4 — le service, en miroir de l'auth web

`AuthService` a déjà `attempt()` / `login()` / `logout()` pour le guard web. On ajoute les équivalents JWT, dans la **même classe** (même domaine métier, deux mécanismes) :

```php
public function attemptJwt(array $credentials): ?string
{
    $token = Auth::guard('api')->attempt($credentials);

    return $token ?: null;
}

public function refreshJwt(): string
{
    return Auth::guard('api')->refresh();
}

public function logoutJwt(): void
{
    Auth::guard('api')->logout();
}
```

`Auth::guard('api')->attempt($credentials)` vérifie l'email/mot de passe **et** renvoie directement le token signé si c'est bon — contrairement à `Auth::attempt()` (guard web) qui renvoie juste un booléen, parce que la session, elle, se charge de retenir qui tu es.

---

## 6. Étape 5 — le controller API et les routes

```php
// app/Http/Controllers/Api/AuthController.php
public function login(Request $request): JsonResponse
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    $token = $this->auth->attemptJwt($credentials);

    if (! $token) {
        return response()->json(['message' => 'Identifiants invalides.'], 401);
    }

    return $this->respondWithToken($token);
}
```

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
});
```

`auth:api` = le même middleware natif Laravel `auth` que sur le web (`routes/web.php` utilise `middleware('auth')`), juste pointé sur le guard `api` au lieu du défaut. Il fait exactement le travail d'un guard NestJS : il lit le header `Authorization: Bearer <token>`, vérifie la signature, et laisse passer — ou renvoie `401` si le token manque, est invalide ou expiré.

Dernière pièce : dire à Laravel que `routes/api.php` existe (par défaut un projet Laravel 13 fraîchement scaffoldé n'a pas de fichier API) :

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',   // ← ajouté
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

---

## 7. Vérifier que ça marche (sans lomkit, sans Postman)

```bash
curl -X POST http://localhost:19080/api/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"admin@example.com","password":"password"}'
# => {"access_token":"...", "token_type":"bearer", "expires_in":3600}

curl http://localhost:19080/api/me -H 'Accept: application/json'
# => 401 (pas de token)

curl http://localhost:19080/api/me \
  -H "Authorization: Bearer <access_token>" -H 'Accept: application/json'
# => {"id":1,"name":"Admin","email":"admin@example.com", ...}
```

C'est le même aller-retour que le cours 8 décrivait en théorie (section 5) : un premier appel pour le token, puis on le recolle en header sur tout le reste.

---

## À retenir

- **JWT se met en place avant les endpoints métier**, jamais après — une route sans middleware d'auth est publique dès qu'elle existe.
- L'API n'est **pas une traduction 1:1** des routes web ; on n'expose que ce dont un client API a besoin.
- Même provider (`users`), guard différent : session (web) et JWT (api) coexistent sans se marcher dessus.
- `Auth::guard('api')->attempt()` renvoie le **token** ; `Auth::attempt()` (web) renvoie un **booléen** — logique, la session retient déjà l'identité côté serveur, pas besoin de te la repasser.
- `JWT_SECRET` signe les tokens ; ne le commite jamais, comme `APP_KEY`.

⬅️ Retour au [sommaire des cours](README.md) · Prochaine étape : le CRUD séances en API avec **lomkit** (search / mutate).
