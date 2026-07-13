# Cours 8 — API REST, JWT & lomkit (Partie II)

> La Partie II transforme l'appli web en **API REST** sécurisée par JWT. Mêmes règles métier (rôles, permissions, notifications), mais **sans sessions** : chaque requête porte un token.

## 1. Stateful vs stateless

- **Partie I (web)** : session + cookie. Le serveur se souvient que tu es connecté.
- **Partie II (API)** : *stateless*. Aucune session ; chaque requête envoie un **token** dans l'en-tête. Le serveur le vérifie et l'oublie aussitôt.

C'est exactement le modèle que tu connais côté front (Supabase, JWT en header `Authorization`).

## 2. JWT avec tymon/jwt-auth

Un **JWT** (JSON Web Token) est un jeton **signé** que le client renvoie à chaque requête :

```
Authorization: Bearer <token>
```

Le serveur vérifie la signature sans stocker de session. Le token encode l'identité (et une expiration).

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

Le model `User` implémente alors l'interface `JWTSubject` (deux méthodes : `getJWTIdentifier()`, `getJWTCustomClaims()`).

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

C'est l'équivalent d'un DTO / serializer : tu contrôles précisément la forme envoyée au front.

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

> ⚠️ Le contrôle d'accès (permissions du cours 6, Policies) reste **obligatoire** : lomkit ne dispense pas d'autoriser. Tu branches tes permissions dans la Resource.

## 5. Tester l'API

`sail artisan route:list --path=api` pour voir les endpoints, puis un client HTTP (Postman/Insomnia/Bruno, ou `curl`). Flux type :
1. `POST /api/login` → récupérer le token.
2. Rejouer les requêtes avec `Authorization: Bearer <token>`.

---

## À retenir

- API = **stateless**, token JWT dans `Authorization: Bearer` (via `tymon/jwt-auth`, guard `auth:api`).
- **API Resource** = contrôle de la forme du JSON (comme un DTO/serializer).
- **lomkit** industrialise via `search` (lecture) / `mutate` (écriture) décrits en JSON ; tu déclares le permis côté serveur.
- Les **permissions/Policies** restent obligatoires même avec lomkit.

⬅️ Retour au [sommaire des cours](README.md) · Prochaine étape : **monter le vrai projet**.
