# Cours 10 — Tester une app Laravel (ta doctrine, côté back)

> Objectif : écrire des tests **automatiques** qui prouvent que l'app fait ce qu'elle doit — et surtout **refuse ce qu'elle doit refuser**. On reprend exactement ta façon de penser en front (RTL/Vitest, matrice de permissions, zéro commentaire) et on la traduit en Laravel.

---

## 0. Le kit de survie

| En front (ta stack) | En Laravel |
|---|---|
| Vitest / Jest | **PHPUnit** |
| `render(<Page/>)` + Testing Library | **Feature test** : on tape une vraie route HTTP |
| `screen.getByRole(...)` | `assertSee(...)`, `assertJsonFragment(...)` |
| `userEvent.click(...)` | `$this->post(...)`, `$this->put(...)` |
| Mock du store / MSW | `Notification::fake()`, `Storage::fake()` |
| Base réinitialisée entre tests | `use RefreshDatabase;` |
| Se mettre dans la peau d'un user | `$this->actingAs($user)` |

Retiens ça : **un Feature test simule un vrai utilisateur qui appelle une URL**, et on vérifie la réponse ET l'état de la base.

---

## 1. Feature vs Unit

- **Feature test** (99 % de ce qu'on écrit ici) : requête HTTP → réponse. On teste le comportement complet (route + middleware + controller + policy + DB). C'est l'équivalent de tester une page entière en front, pas une fonction isolée.
- **Unit test** : une classe/méthode isolée, sans HTTP ni DB. À réserver à une logique pure et compliquée.

> 💡 Comme en front : tu testes le composant tel que l'utilisateur le vit, pas ses fonctions internes une par une.

---

## 2. L'ossature d'un test

```php
class SeanceCrudTest extends TestCase
{
    use RefreshDatabase;              // vide + remigre la base à chaque test

    public function test_admin_can_create_a_seance(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)                       // je suis cet utilisateur
            ->post(route('seances.store'), [...])      // j'appelle la route
            ->assertRedirect();                        // je vérifie la réponse

        $this->assertDatabaseHas('seances', ['name' => 'Pilates']); // et la base
    }
}
```

Trois choses seulement : **qui** (`actingAs`), **quelle action** (`post/put/delete/get`), **quel effet attendu** (les `assert*`).

- `User::factory()->create()` = ta `factory` front, elle fabrique un user valide sans que tu remplisses tout.
- `RefreshDatabase` = chaque test repart d'une base propre. Zéro pollution entre tests (comme un `beforeEach` qui reset).
- Le nom de la méthode **commence par `test_`** et **décrit le comportement**. C'est ton `it('...')`.

---

## 3. Le cœur : la matrice de permissions

C'est **ta** règle, la même qu'en front : pour toute unité protégée, on pilote l'état de permission et on teste **autorisé ET refusé**. On ne teste pas « ça marche », on teste **qui a le droit**.

Dans ce projet, la Policy dit :

| Action | admin | manager | coach (sa séance) | coach (autre) | collaborator |
|---|:--:|:--:|:--:|:--:|:--:|
| créer | ✅ | ✅ | ✅ | — | ❌ |
| modifier | ✅ | ✅ | ✅ | ❌ | ❌ |
| annuler | ✅ | ✅ | ✅ | ❌ | ❌ |
| supprimer | ✅ | ✅ | ✅ | ❌ | ❌ |

Chaque ✅ devient un test qui attend `assertRedirect()`, chaque ❌ un test qui attend `assertForbidden()` (403). On **pilote le rôle** (`assignRole(...)`), pas un catalogue de personas figés.

```php
public function test_collaborator_cannot_create_a_seance(): void
{
    $this->actingAs($this->userWithRole('collaborator'))
        ->post(route('seances.store'), $this->payload())
        ->assertForbidden();

    $this->assertDatabaseCount('seances', 0);   // et rien n'a été créé
}
```

> ⚠️ Le refus ne se prouve pas qu'avec le 403 : on vérifie aussi que **l'effet n'a pas eu lieu** (`assertDatabaseCount(0)`). Un contrôleur peut renvoyer 403 après avoir déjà écrit — ce double contrôle le débusque.

---

## 4. Exhaustif, pas happy-path (la vraie différence)

On l'a fait en deux temps sur le même fichier, exprès, pour voir l'écart.

**v1 — le réflexe débutant (à ne pas faire) :**

```php
public function test_delete_seance(): void
{
    // préparation
    $admin = User::factory()->create()->assignRole('admin');
    $seance = Seance::factory()->create();

    // suppression
    $this->actingAs($admin)->delete('/seances/'.$seance->id);

    // on vérifie que tout s'est bien passé
    $this->assertTrue(true);          // ← ne teste RIEN, le test est vert pour rien
}
```

Les défauts : commentaires partout, seulement l'admin, seulement le cas qui marche, et une assertion mensongère. **Un test vert qui ne prouve rien est pire que pas de test** : il donne une fausse confiance.

**v2 — la doctrine :**

```php
public function test_admin_can_delete_a_seance(): void
{
    $seance = $this->seanceOwnedBy($this->userWithRole('coach'));

    $this->actingAs($this->userWithRole('admin'))
        ->delete(route('seances.destroy', $seance))
        ->assertRedirect();

    $this->assertSoftDeleted('seances', ['id' => $seance->id]);
}
```

Puis on ajoute tous les autres cas : le coach sur SA séance (✅), le coach sur celle d'un autre (❌), le collaborator (❌), les gardes (invité → login), les bords de validation (`name` requis, `ended_at` après `started_at`, capacité ≥ 1), et la règle métier (le coach est forcé comme coach de sa séance).

> 🐛 **Ce que la matrice a fait sortir** : en écrivant la ligne « manager », il a fallu lire la Policy — et le seeder ne donnait **pas** `create` au manager. Le happy-path ne l'aurait jamais montré. Un test exhaustif t'oblige à confronter le comportement réel.

---

## 5. Zéro commentaire

Comme dans tout ton code. L'intention passe par **le nom du test**, pas par un `// arrange`. Si un nom ne suffit plus à dire ce que le test fait, c'est que le test fait trop de choses — coupe-le en deux.

```php
test_coach_cannot_update_another_coachs_seance()   // ✅ le nom EST la spec
```

---

## 6. Les assertions utiles (mémo)

| But | Assertion |
|---|---|
| Réponse redirige | `assertRedirect()` / `assertRedirect(route('login'))` |
| Accès refusé | `assertForbidden()` (403) |
| Non connecté sur route API | `assertUnauthorized()` (401) |
| Réponse OK | `assertOk()` (200) |
| Erreurs de validation | `assertSessionHasErrors('champ')` |
| Ligne présente en base | `assertDatabaseHas('table', [...])` |
| Ligne absente | `assertDatabaseMissing('table', [...])` |
| Nombre de lignes | `assertDatabaseCount('table', 0)` |
| Soft delete posé | `assertSoftDeleted('table', ['id' => …])` |
| JSON : nb d'éléments | `assertJsonCount(2)` |
| JSON : contient | `assertJsonFragment(['id' => …])` |
| Qui est connecté | `assertAuthenticatedAs($user)` / `assertGuest()` |

---

## 7. Simuler les effets externes (les *fakes*)

On ne veut pas **vraiment** envoyer un mail ni écrire un fichier pendant un test. On les remplace, exactement comme MSW/mock en front.

**Notifications :**
```php
Notification::fake();
// ... on crée une séance ...
Notification::assertSentTo($coach, SeanceCreatedNotification::class);
```

**Upload de fichier :**
```php
Storage::fake('public');
$file = UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf');
// ... on poste la séance avec ce fichier ...
$this->assertCount(1, Seance::firstOrFail()->getMedia('files'));
```

`fake()` intercepte tout : rien ne part pour de vrai, mais on peut **vérifier que ça aurait été envoyé/écrit**.

---

## 8. Lancer les tests

```bash
sail artisan test --compact                          # toute la suite
sail artisan test --compact tests/Feature/SeanceCrudTest.php   # un fichier
sail artisan test --filter=test_manager_can_create   # un test précis
```

> ⚙️ Les tests tournent sur une base séparée (`testing`, dans `phpunit.xml`) — ils ne touchent jamais tes données de dev.

---

## 9. Le plan d'abord (`task-test.md`)

Comme en front : avant de coder les tests, on liste les blocs à couvrir dans un `task-test.md`, avec des cases à cocher. Une raison de sauter un cas va **dans le plan**, jamais en commentaire dans le code. On exécute ensuite **bloc par bloc**, en gardant la suite verte.

Blocs couverts pour « Séances de sport » : accès/auth, CRUD (matrice), inscription/waitlist/conflit, gestion des participants, flux calendrier, notifications, upload.

---

## 10. Récap

- Un **Feature test** = un vrai user tape une URL, on vérifie **réponse + base**.
- **Matrice de permissions** : autorisé ✅ ET refusé ❌, on pilote le rôle.
- **Exhaustif** : gardes, validation, règles métier — pas juste le cas qui marche.
- **Zéro commentaire** : le nom du test est la spec.
- **Prouver l'effet** : un 403 se double d'un `assertDatabaseCount(0)`.
- **Fakes** pour mails et fichiers.
- **Plan d'abord**, exécution bloc par bloc, suite toujours verte.
