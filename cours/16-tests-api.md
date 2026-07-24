# Cours 16 — Tester la Partie II (JWT, lomkit, Access Control)

> Prérequis : [Cours 10](10-tests.md) — la doctrine (Feature test, matrice de permissions, exhaustif, zéro commentaire, fakes) ne change pas. Ce cours ajoute uniquement ce qui est **spécifique à l'API** : s'authentifier avec un token au lieu d'une session, parler le format lomkit (`search`/`mutate`/`destroy`/`actions`), et tester un Access Control basé sur des Perimeters plutôt qu'une Policy à la main.

---

## 0. Ce qui change, ce qui ne change pas

| | Partie I (web) | Partie II (API) |
|---|---|---|
| S'authentifier dans un test | `$this->actingAs($user)` | header `Authorization: Bearer <token>` |
| Appeler une route | `$this->post(route('seances.store'), [...])` | `$this->postJson('/api/seances/mutate', [...])` |
| Refus d'accès | `assertForbidden()` (403 avec redirection Blade) | `assertForbidden()` / `assertUnauthorized()` (JSON pur) |
| Doctrine (matrice, exhaustif, zéro commentaire, fakes) | ✅ | ✅ **identique** |

Rien de la doctrine du cours 10 ne saute. On ajoute une couche de traduction HTTP.

---

## 1. S'authentifier dans un test API — trois façons, une seule à utiliser

**Façon lente (à éviter en masse)** : passer par `/api/login` comme un vrai client, à chaque test. Ça marche, mais ça re-teste le login à chaque fois — bruit inutile si ce n'est pas le sujet du test.

**Façon rapide (celle qu'on utilise partout sauf dans `AuthTest`)** : générer le token directement, sans passer par l'endpoint.

```php
$user = $this->userWithRole('coach');
$token = auth('api')->login($user);

$this->withHeader('Authorization', "Bearer {$token}")
    ->getJson('/api/seances/search')
    ->assertOk();
```

`auth('api')->login($user)` fait exactement ce que fait `AuthController::login()` en interne (signer un token pour ce user), sans repasser par la route HTTP. C'est l'équivalent d'un `createTestUser()` qui pose directement un cookie de session signé en front, plutôt que de simuler le formulaire de login à chaque test.

> 💡 Un seul fichier — `AuthTest` — a le droit de taper `/api/login` en HTTP : c'est lui qui teste le login. Tous les autres fichiers font confiance à `AuthTest` et utilisent le raccourci `auth('api')->login()`.

**Façon "je veux tester le rejet du token"** : ne pas mettre de header, ou en mettre un invalide.

```php
$this->getJson('/api/seances/search')->assertUnauthorized();          // pas de header → 401
$this->withHeader('Authorization', 'Bearer invalide')
    ->getJson('/api/seances/search')->assertUnauthorized();
```

---

## 2. Le format lomkit — ce que le client envoie

Contrairement aux routes web (une route par action), lomkit expose des endpoints génériques où **le corps JSON décrit la requête**. Le mémo côté cours 14 : *toi* tu déclares `fields()`/`relations()`/`rules()` dans la Resource, *le client* envoie des filtres/includes dans le body. Un test, c'est un client — donc les tests écrivent ce corps JSON.

**`search`** (lecture) :

```php
$this->withHeader('Authorization', "Bearer {$token}")
    ->postJson('/api/seances/search', [
        'search' => [
            'filters' => [
                ['field' => 'coach_id', 'operator' => '=', 'value' => $coach->id],
            ],
            'limit' => 10,
        ],
    ])
    ->assertOk()
    ->assertJsonCount(1, 'data');
```

⚠️ Tout — `filters`, `includes`, `limit` — vit sous une clé racine `search`. Un corps qui oublie ce wrapper n'est **pas rejeté** (la clé `search` n'est pas `required`) : la requête réussit silencieusement, sans filtrer. C'est le genre d'erreur qu'on ne voit qu'en testant vraiment le comportement (un `assertJsonCount` sur le mauvais total l'aurait fait sortir).

**`mutate`** (create/update, en une seule opération pilotée par un champ `operation`) :

```php
$this->withHeader('Authorization', "Bearer {$token}")
    ->postJson('/api/seances/mutate', [
        'mutate' => [[
            'operation' => 'create',
            'attributes' => [
                'name' => 'Pilates',
                'coach_id' => $coach->id,
                'place_id' => $this->place->id,
                'started_at' => now()->addDay(),
                'ended_at' => now()->addDay()->addHour(),
            ],
        ]],
    ])
    ->assertOk();

$this->assertDatabaseHas('seances', ['name' => 'Pilates']);
```

Update, c'est la même forme avec `'operation' => 'update'` et un `'key' => $seance->id` en plus des `attributes`.

**`destroy`** (verbe `DELETE`, sur l'URL de base — pas de suffixe `/destroy`) :

```php
$this->withHeader('Authorization', "Bearer {$token}")
    ->deleteJson('/api/seances', ['resources' => [$seance->id]])
    ->assertOk();

$this->assertSoftDeleted('seances', ['id' => $seance->id]);
```

**Une Action** (`cancel-seance`, `register`…) — même principe, un endpoint dédié par `uriKey`. Pas de clé `resources` ici : les modèles ciblés se sélectionnent avec le **même** `search.filters` que l'endpoint `search`, et les champs (`fields()` déclarés sur l'Action) s'envoient en **liste `{name, value}`**, pas en objet associatif :

```php
$this->withHeader('Authorization', "Bearer {$token}")
    ->postJson('/api/seances/actions/cancel-seance', [
        'search' => ['filters' => [['field' => 'id', 'operator' => '=', 'value' => $seance->id]]],
        'fields' => [],
    ])
    ->assertOk();

$this->assertNotNull($seance->fresh()->cancelled_at);
```

Pour une Action qui déclare des champs (`AddParticipantAction::fields()` → `user_id`) :

```php
->postJson('/api/seances/actions/add-participant', [
    'search' => ['filters' => [['field' => 'id', 'operator' => '=', 'value' => $seance->id]]],
    'fields' => [['name' => 'user_id', 'value' => $participant->id]],
])
```

> ⚠️ Seul `destroy` (`DELETE /api/seances`) utilise une clé `resources` (liste brute d'ids) — `search`, `mutate` et les Actions n'utilisent jamais cette clé. Facile à confondre, seul un test qui échoue au mauvais endroit le révèle (`resources` envoyé à une Action est silencieusement ignoré, pas rejeté).

> 💡 Analogie front : c'est la différence entre du REST "une route = un verbe" et un unique endpoint GraphQL où la query/mutation vit dans le body. lomkit choisit ce deuxième modèle pour le CRUD générique.

---

## 3. La matrice de permissions ne change pas de forme, juste de porte d'entrée

Le cours 10 dit : pour toute action protégée, piloter le rôle et tester autorisé ✅ ET refusé ❌, puis prouver l'effet (pas juste le code HTTP). En API, le refus attendu est `assertForbidden()` (403, policy qui dit non) — le token est valide, c'est le **rôle** qui ne l'autorise pas. Un `assertUnauthorized()` (401), lui, veut dire "pas de token/token invalide", pas "pas le droit".

```php
public function test_collaborator_cannot_update_a_seance(): void
{
    $seance = $this->seanceOwnedBy($this->userWithRole('coach'));
    $token = auth('api')->login($this->userWithRole('collaborator'));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/seances/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $seance->id, 'attributes' => ['name' => 'Hack']]],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('seances', ['name' => 'Hack']);
}
```

---

## 4. Tester un Access Control (Perimeters) — ce que ça change par rapport à une Policy à la main

Le cours 15 a expliqué le piège : `Seance::controlled()` est un **scope de filtrage de liste**, complètement indépendant de la Policy utilisée par `search`/`mutate`/`destroy`. Comme ce scope n'est branché nulle part dans ce projet, l'autorisation de `search` passe uniquement par `SeancePolicy::viewAny()` — qui, via `SeanceControl::allowedForMethod()`, renvoie `true` pour **tout le monde**, y compris un collaborateur. Un test qui ne vérifierait que "collaborator peut appeler `search`" manquerait le vrai bug : **il verra toutes les séances, pas seulement les siennes**.

C'est exactement le genre de chose qu'un test exhaustif fait sortir (cf. section 4 du cours 10, "le happy-path ne l'aurait jamais montré"). Ici, on écrit le test qui **documente ce comportement en l'état actuel du code** — pas pour dire que c'est juste, mais pour que toute correction future fasse échouer ce test (donc oblige à le mettre à jour consciemment) :

```php
public function test_collaborator_sees_every_seance_via_search_because_controlled_scope_is_not_wired(): void
{
    Seance::factory()->for($this->userWithRole('coach'), 'coach')->create();
    Seance::factory()->for($this->userWithRole('coach'), 'coach')->create();
    $token = auth('api')->login($this->userWithRole('collaborator'));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/seances/search', [])
        ->assertOk()
        ->assertJsonCount(2, 'data');
}
```

À côté, on teste ce qui, lui, **est** branché : les Actions `cancel-seance`/`manageParticipants` passent par `Gate::authorize()` écrit à la main dans l'Action, donc là la matrice de permissions s'applique normalement (coach sur sa séance ✅, coach sur celle d'un autre ❌, collaborator ❌ partout, admin/manager ✅ partout).

> 💡 Un Perimeter n'est pas une Policy : il répond à deux questions différentes — `allowed(user, method)` ("ce rôle a-t-il seulement le droit d'essayer ?") et `query(builder, user)` ("si oui, sur quel sous-ensemble ?"). Tester un Control, c'est tester ces deux réponses séparément, pas juste un 200/403 global.

---

## 5. Les effets de bord à vérifier (events, pas mails)

`SeanceResource::mutated()`/`destroyed()` dispatchent `SeanceCreated`/`SeanceDeleted` à la main (parce que `mutate`/`destroy` court-circuitent `SeanceService`). `CancelSeanceAction`, lui, passe par `SeanceService::cancel()` qui dispatch `SeanceCancelled`. Trois façons différentes d'arriver à un event, donc trois choses à couvrir séparément — sinon une régression sur un seul chemin (par exemple si quelqu'un enlève le hook `mutated()`) passe inaperçue :

```php
Event::fake([SeanceCreated::class]);

$this->withHeader('Authorization', "Bearer {$token}")
    ->postJson('/api/seances/mutate', ['mutate' => [['operation' => 'create', 'attributes' => [...]]]])
    ->assertOk();

Event::assertDispatched(SeanceCreated::class);
```

Même principe que `Notification::fake()` au cours 10 — on ne vérifie pas qu'un mail part vraiment, on vérifie que le bon signal a été émis.

---

## 6. Pièges spécifiques à couvrir (issus du cours 14)

- **`guardName()` forcé sur `'web'`** : un test qui logue un user en JWT (guard `api`) puis vérifie `$user->can('create seances')` doit passer — si ce n'est pas le cas, c'est que le fix `guardName()` a régressé. Un test dédié, minimal, protège ce point précis.
- **Policies minimales sur les modèles inclus en relation** (`UserResource`, `PlaceResource` inclus via `relations()`) : un `search` avec `'includes' => [['relation' => 'coach']]` doit renvoyer les données du coach, pas une erreur d'autorisation silencieuse — à couvrir par un test qui inclut la relation et vérifie sa présence dans le JSON (`assertJsonPath` sur `data.0.coach.id`).
- **Le cache d'autorisation lomkit** (`CACHE_STORE=database` cassait avec un objet `Response`) : rien à tester ici, c'est de la config d'environnement (`phpunit.xml` force déjà `CACHE_STORE=array` en test) — mais bon à savoir si un test échoue bizarrement en local avec un autre driver de cache.

---

## 7. Piège rencontré en écrivant ces tests : ne pas chaîner deux Actions différentes pour préparer un état

Un test comme *"le coach retire un participant déjà inscrit"* tente naturellement d'enchaîner deux appels HTTP dans le même test : d'abord `register` (pour créer l'inscription), puis `remove-participant` (l'action réellement testée). Fait comme ça, le second appel a planté avec une erreur PHP (`Undefined array key "user_id"`) alors que le même enchaînement en deux vraies requêtes HTTP (curl) fonctionnait très bien. La cause : dans un test PHPUnit, les deux appels simulés partagent le **même conteneur applicatif** (contrairement à deux vraies requêtes, chacune dans son propre process) — un objet interne à lomkit n'est pas correctement réinitialisé entre les deux, et la deuxième Action se retrouve avec des champs mal résolus.

La leçon, réutilisable au-delà de ce cas précis : **ne fabrique une précondition via l'API que si c'est elle que tu testes.** Sinon, pose l'état directement en base :

```php
$seance->participants()->attach($participant->id, ['status' => 'registered', 'position' => 0]);
```

au lieu de rappeler l'action `register`. C'est plus rapide, plus isolé, et ça évite ce genre d'artefact propre à l'exécution en un seul process de test.

---

## 8. Où ranger tout ça

Convention adoptée pour ce projet : les tests Partie II vivent sous `tests/Feature/Api/`, un fichier par domaine — même découpage que le code (`AuthController` → `AuthTest`, CRUD lomkit → `SeanceRestTest`, Actions → `SeanceActionsTest`, Perimeters → `SeanceAccessControlTest`). Les helpers déjà écrits pour la Partie I (`userWithRole()`, `seanceOwnedBy()`, `payload()`) sont réutilisés tels quels — c'est le même modèle, la même Policy, juste une autre porte d'entrée HTTP.

---

## Récap

- Auth dans les tests : `auth('api')->login($user)` pour aller vite, `/api/login` en HTTP réservé au fichier qui teste le login lui-même.
- Le corps JSON de `search`/`mutate`/`destroy`/`actions/{uriKey}` est la requête — c'est au test de l'écrire, comme un vrai client.
- La matrice de permissions (cours 10) ne change pas : `assertForbidden()` = rôle refusé, `assertUnauthorized()` = token absent/invalide, ce n'est pas la même chose.
- Un Perimeter se teste en deux temps : `allowed()` (le droit d'essayer) et `query()` (le filtrage de liste) — surtout quand, comme ici, un des deux n'est pas branché : le test doit alors documenter le comportement réel, pas l'idéal.
- Trois chemins d'events différents (`mutated`, `destroyed`, `SeanceService::cancel`) = trois tests `Event::fake()` séparés.

⬅️ Retour au [sommaire des cours](README.md) · Voir aussi [Cours 10](10-tests.md) pour la doctrine générale et [Cours 15](15-lomkit-access-control.md) pour le détail du piège `controlled()`.
