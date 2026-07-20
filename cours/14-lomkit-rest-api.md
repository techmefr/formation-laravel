# Cours 14 — lomkit/laravel-rest-api en pratique (Partie II)

> But : brancher le CRUD séances en API avec **lomkit**, sans réécrire un controller par endpoint, en réutilisant les policies déjà écrites ([Cours 6](06-auth-permissions.md), [Cours 11](11-auth-a-la-main.md)). Rappel théorique : [Cours 8](08-api-rest-jwt-lomkit.md). Prérequis : le guard JWT du [Cours 13](13-jwt-implementation.md).

---

## 1. La carte : où vivent les fichiers lomkit

| Rôle | Emplacement |
|---|---|
| La Resource (déclare champs/relations/rules) | `app/Rest/Resources/SeanceResource.php` |
| Le controller générique | `app/Rest/Controllers/SeancesController.php` |
| Les actions métier (cancel, inscription…) | `app/Rest/Actions/*.php` |
| L'enregistrement des routes | `routes/api.php` (`Rest::resource(...)`) |
| La config du package | `config/rest.php` (publiée) |

Générés par artisan, jamais écrits à la main depuis zéro :

```bash
sail artisan rest:resource SeanceResource --model=Seance
sail artisan rest:controller SeancesController --resource=SeanceResource
sail artisan rest:action CancelSeanceAction
```

---

## 2. Deux niveaux à ne pas confondre : déclaration (toi) vs requête (le client)

Une Resource lomkit expose toujours le même jeu de méthodes, dans le même ordre — pas par convention arbitraire, mais parce que chaque méthode vient d'un **trait précis** combiné dans la classe `Resource` (même mécanique que `HasRoles` sur `User`, cours 13) :

| Méthode | Vient du trait | Rôle |
|---|---|---|
| `fields()` | `ConfiguresRestParameters` | champs exposés — et donc filtrables/triables par ce seul fait |
| `scopes()` | `ConfiguresRestParameters` | scopes Eloquent exposables |
| `limits()` | `ConfiguresRestParameters` | tailles de page autorisées |
| `relations()` | `Relationable` | relations exposables (et donc "includable") |
| `rules()` / `createRules()` / `updateRules()` | `Rulable` | validation appliquée sur `mutate` |
| `scoutFields()` / `scoutInstructions()` | `Scoutable` | intégration Laravel Scout (section 8, pas utilisée ici) |
| `actions()` | `Actionable` | tes actions métier (section 6) |
| `instructions()` | `Instructionable` | variante plus légère d'action, pas couverte ici |

**Il n'existe pas** de méthode `filters()`, `includes()` ou `excludes()` à écrire sur la Resource — c'est le piège de vocabulaire le plus fréquent. Ces mots-là désignent ce que **le client** envoie dans le corps JSON de sa requête `search`, pas ce que toi tu déclares :

```jsonc
{
  "search": {
    "filters": [{"field": "coach_id", "operator": "=", "value": 3}],
    "includes": [{"relation": "coach"}]
  }
}
```

Le lien entre les deux niveaux est mécanique : `filters` ne peut cibler que des champs listés dans `fields()`, `includes` ne peut cibler que des relations listées dans `relations()`. Rien à activer en plus côté déclaration — la présence dans `fields()`/`relations()` suffit à rendre le champ filtrable / la relation includable.

---

## 3. La Resource : ce que le client a le droit de demander

```php
// app/Rest/Resources/SeanceResource.php
public function fields(RestRequest $request): array
{
    return ['id', 'name', 'coach_id', 'place_id', 'started_at', 'ended_at', 'max_participants', 'cancelled_at'];
}

public function relations(RestRequest $request): array
{
    return [
        BelongsTo::make('coach', UserResource::class),
        BelongsTo::make('place', PlaceResource::class),
        BelongsToMany::make('participants', UserResource::class),
    ];
}

public function rules(RestRequest $request): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'place_id' => ['required', 'exists:places,id'],
        // ...
    ];
}
```

> 💡 Le réflexe du cours 8 se confirme en pratique : ce qui n'est pas dans `fields()`/`relations()` **n'existe pas** pour le client, filtre y compris — pas besoin de déclarer un `filters()` séparé, un champ listé dans `fields()` est filtrable par ce seul fait.

Deux endpoints génériques suffisent alors pour tout le CRUD :

```jsonc
// POST /api/seances/search — lire, avec includes de relations
{"search": {"limit": 10, "filters": [{"field": "coach_id", "operator": "=", "value": 3}], "includes": [{"relation": "coach"}]}}

// POST /api/seances/mutate — créer/modifier
{"mutate": [{"operation": "create", "attributes": {"name": "Yoga", "place_id": 2, "coach_id": 3, "started_at": "...", "ended_at": "..."}}]}
```

---

## 4. Piège n°1 : le cache d'autorisation casse tout, même pour un utilisateur valide

lomkit vérifie automatiquement `Gate::inspect('viewAny', Seance::class)` sur `search`, et `Gate::inspect('create'|'update'|'delete', $seance)` sur `mutate`/`destroy` — c'est ta `SeancePolicy` du cours 6, sans rien à réécrire. Mais **par défaut, lomkit met ce résultat en cache** (`config/rest.php`, `authorizations.cache.enabled = true`).

Problème concret rencontré : avec `CACHE_STORE=database`, le cache sérialise l'objet `Illuminate\Auth\Access\Response` en base, et l'`unserialize()` plante (`incomplete object`) — l'autorisation échoue alors même pour un admin qui a parfaitement le droit. Fix : désactiver ce cache, pas la peine ici vu le volume de requêtes d'une formation.

```php
// config/rest.php
'authorizations' => [
    'cache' => ['enabled' => false],
],
```

---

## 5. Piège n°2 : une relation incluse a besoin de SA PROPRE policy

Inclure `coach` (relation vers `User`) ou `place` déclenche **aussi** `Gate::inspect('viewAny', User::class)` / `Gate::inspect('viewAny', Place::class)` — pas seulement sur `Seance`. Sans `UserPolicy`/`PlacePolicy`, Laravel n'a personne à qui poser la question et refuse par défaut.

```php
// app/Policies/UserPolicy.php et PlacePolicy.php
public function viewAny(User $user): bool { return true; }
public function view(User $user, User $model): bool { return true; }
```

> 💡 Règle à retenir : **chaque modèle exposé via une relation lomkit a besoin d'au moins `viewAny`/`view` sur sa propre policy**, même si tu n'exposes jamais cette resource en top-level.

---

## 6. Piège n°3 : `$user->can(...)` peut mentir selon le guard actif

`SeancePolicy::create()` fait `$user->can('create seances')`. Ça marchait côté web (guard `web`), mais échouait via JWT — pas un bug de policy, un piège spatie/laravel-permission : **les rôles et permissions sont stockés par guard**, et le guard par défaut devient `api` après authentification JWT (Laravel appelle `Auth::shouldUse('api')` en interne). Spatie cherchait alors des permissions en guard `api`, qui n'existent pas (seedées en `web`).

Fix — dire à spatie d'ignorer le guard actif pour ce modèle, vu que c'est le même utilisateur des deux côtés :

```php
// app/Models/User.php
public function guardName(): string
{
    return 'web';
}
```

> 💡 Symptôme à reconnaître : une policy qui renvoie visiblement `true` en debug, mais dont l'appel `$user->can('permission custom')` échoue quand même — pense à vérifier le guard actif (`config('auth.defaults.guard')`) avant de soupçonner la policy elle-même.

---

## 7. Les Actions : le CRUD générique ne suffit pas

`search`/`mutate` couvrent create/update/delete génériques, mais pas les actions métier (annuler une séance, s'inscrire, gérer les participants d'un autre). lomkit prévoit des **Actions** : une classe par action, montée sur `POST /api/{resource}/actions/{uriKey}`.

```php
// app/Rest/Actions/CancelSeanceAction.php
public function handle(array $fields, Collection $models): void
{
    foreach ($models as $seance) {
        Gate::authorize('cancel', $seance);   // <- pas automatique, à faire toi-même
        $this->seances->cancel($seance);
    }
}

public function fields(RestRequest $request): array
{
    return [];   // pas de paramètre pour celle-ci
}
```

```php
// app/Rest/Resources/SeanceResource.php
public function actions(RestRequest $request): array
{
    return [
        app(CancelSeanceAction::class),
        app(RegisterAction::class),
        app(UnregisterAction::class),
        app(AddParticipantAction::class),
        app(RemoveParticipantAction::class),
    ];
}
```

> ⚠️ **Piège n°4** : contrairement à `search`/`mutate`/`destroy`, lomkit **n'autorise pas automatiquement** une Action. `Gate::authorize(...)` est à appeler toi-même dans `handle()`, pour chaque modèle impacté. L'oublier = endpoint ouvert à tout utilisateur authentifié.

Deux familles d'actions, reprises du contrôleur web (cours 12) :
- **Self-service** (`RegisterAction`/`UnregisterAction`) : aucune policy — n'importe quel utilisateur connecté gère sa propre inscription, exactement comme `InscriptionController` côté web.
- **Gérées par le staff** (`AddParticipantAction`/`RemoveParticipantAction`) : gardées par `Gate::authorize('manageParticipants', $seance)`, comme `ParticipantController` côté web. Elles prennent un champ `user_id` :

```php
public function fields(RestRequest $request): array
{
    return ['user_id' => ['required', 'exists:users,id']];
}
```

Appel type :

```bash
curl -X POST /api/seances/actions/cancel-seance \
  -d '{"search": {"filters": [{"field": "id", "operator": "=", "value": 42}]}}'

curl -X POST /api/seances/actions/add-participant \
  -d '{"search": {"filters": [{"field": "id", "operator": "=", "value": 42}]}, "fields": [{"name": "user_id", "value": 7}]}'
```

Le `uriKey` d'une action se déduit du nom de classe (`CancelSeanceAction` → `cancel-seance`, `AddParticipantAction` → `add-participant`) — pas besoin de le déclarer, juste de connaître la règle si tu veux le deviner sans regarder `route:list`.

---

## 8. La documentation OpenAPI, gratuite

lomkit génère une doc Swagger à partir de tes Resources — rien à écrire :

```bash
sail artisan rest:documentation
```

Consultable sur `http://localhost:19080/api-documentation` (régénérée à chaque changement de Resource ; le JSON produit dans `public/vendor/rest/` n'est pas versionné, comme un asset compilé).

---

## 9. Pour info : `scoutFields` (Laravel Scout), pas utilisé ici

La Resource lomkit a aussi une méthode `scoutFields()` (vue vide dans `SeanceResource`) : elle sert à brancher un `search` sur un moteur de recherche full-text via **Laravel Scout** (Algolia, Meilisearch, ou un driver Elasticsearch communautaire), en complément — pas remplacement — des filtres SQL classiques. On ne l'utilise pas dans ce projet (pas de moteur de recherche installé), mais c'est ce que ce nom désigne si tu le recroises dans le code du package.

---

## À retenir

- Une Resource lomkit = `fields()` + `relations()` + `rules()` ; le CRUD (`search`/`mutate`/`destroy`) authorise automatiquement via **tes policies existantes**, à condition que **chaque modèle exposé** (y compris via une relation) ait au moins `viewAny`/`view`.
- Pas de `filters()`/`includes()`/`excludes()` à écrire : c'est ce que **le client** envoie dans sa requête `search`, pas ce que toi tu déclares. La déclaration (`fields()`/`relations()`) autorise, la requête exploite.
- Le cache d'autorisation lomkit est une source de bugs difficiles à diagnostiquer (objets sérialisés) — à désactiver si `CACHE_STORE` n'est pas `array`/`redis`.
- spatie/laravel-permission range les permissions par guard ; force `guardName()` sur `User` si le même utilisateur doit garder ses rôles quel que soit le guard qui l'a authentifié.
- Les **Actions** lomkit ne sont **jamais auto-autorisées** : `Gate::authorize()` est à écrire toi-même dans `handle()`.
- `scoutFields()` = intégration Laravel Scout, hors périmètre ici.

⬅️ Retour au [sommaire des cours](README.md) · Prochaine étape : **lomkit/laravel-access-control** (Controls/Perimeters), pour remplacer les Policies à la main comme en prod StackTim.
