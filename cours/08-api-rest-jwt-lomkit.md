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
