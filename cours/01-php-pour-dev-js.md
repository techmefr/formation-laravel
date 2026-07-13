# Cours 1 — PHP pour un dev JS/TS

> Objectif : pouvoir **lire n'importe quel fichier Laravel** sans buter sur la syntaxe. Tu connais déjà la programmation (JS/TS, Nest, React…) — on ne fait que la **traduction** vers PHP, une notion à la fois.

---

## 0. Le kit de survie (à lire en premier)

Avant tout le détail, ces 5 réflexes suffisent à déchiffrer 90 % du code :

| Réflexe | JS/TS | PHP |
|---|---|---|
| Les variables commencent par `$` | `const nom = 'x'` | `$nom = 'x';` |
| Chaque ligne finit par `;` | optionnel | **obligatoire** |
| On colle des chaînes avec `.` | `a + b` | `$a . $b` |
| Accès objet avec `->` | `obj.prop` | `$obj->prop` |
| Import en haut du fichier | `import { X } from '…'` | `use App\X;` |

Garde ce tableau en tête, le reste ce sont des détails.

---

## 1. Comment PHP s'exécute (30 secondes)

Un fichier `.php` tourne **à chaque requête HTTP**, produit une réponse, puis **oublie tout**. Il n'y a pas de serveur qui reste allumé en mémoire comme ton `node` — c'est *stateless*.

> 💡 Conséquence : l'état vit dans la **base de données**, pas dans des variables globales. Comme quand tu fais du serverless (une fonction par requête).

```php
<?php                       // ouvre un fichier PHP
$nom = "Gaetan";
echo "Bonjour $nom";        // affiche : Bonjour Gaetan
```

---

## 2. Variables : juste `$`

Pas de `let`, `const`, `var`. Une variable = `$` + son nom. Type dynamique, comme JS.

```php
$age    = 30;        // JS : let age = 30
$actif  = true;
$nom    = "yoga";
$rien   = null;
```

C'est tout. Le `$` est **toujours** là, même quand tu réutilises la variable (`$age = $age + 1;`).

---

## 3. Les chaînes de caractères

Deux pièges pour un dev JS.

**Piège 1 — on concatène avec `.`, pas avec `+`.** En PHP, `+` c'est *uniquement* l'addition de nombres.

```php
$message = "Séance " . "yoga";     // ✅ "Séance yoga"
// $message = "Séance " + "yoga";  // ❌ erreur / résultat absurde
```

**Piège 2 — guillemets doubles vs simples.**

```php
$nom = "Gaetan";
echo "Bonjour $nom";     // doubles → interpole : "Bonjour Gaetan"
echo 'Bonjour $nom';     // simples → littéral : "Bonjour $nom"
echo "Coach : {$user->nom}";   // {} pour une expression, comme ${...} en JS
```

Règle simple : guillemets **doubles** = comme les backticks JS (avec variables dedans).

---

## 4. Les tableaux — LE concept à maîtriser

En JS tu as deux choses : les tableaux `[]` et les objets `{}`. **En PHP il n'y a qu'un seul type : `array`**, qui fait les deux.

### a) En mode liste (comme un array JS)

```php
$roles = ['admin', 'coach', 'collaborator'];
echo $roles[0];          // 'admin'
$roles[] = 'invite';     // push
```

### b) En mode "objet" (clé => valeur)

```php
$user = [
    'name'  => 'Gaetan',      // en JS : { name: 'Gaetan' }
    'email' => 'g@xefi.fr',
];
echo $user['name'];           // 'Gaetan'  — attention : ['clé'], pas .name
```

La flèche `=>` remplace le `:` des objets JS. Et on lit toujours avec `['clé']`, jamais `.clé`.

### Table de traduction

| JavaScript | PHP |
|---|---|
| `[1, 2, 3]` | `[1, 2, 3]` |
| `{ name: 'x' }` | `['name' => 'x']` |
| `obj.name` | `$obj['name']` |
| `arr.map(f)` | `array_map($f, $arr)` |
| `arr.filter(f)` | `array_filter($arr, $f)` |
| `arr.length` | `count($arr)` |

> 💡 En Laravel tu manipuleras surtout des **Collections** : `collect($arr)->map(...)->filter(...)`. Ça te redonne exactement la syntaxe chaînée que tu aimes en JS. Mais dessous, c'est un `array`.

---

## 5. Comparaisons : la bonne nouvelle

Comme en JS : `==` compare mollement (avec conversion de type), `===` compare strictement. **Utilise `===`**, exactement le même réflexe qu'en JS.

```php
0 == '0'     // true  (mou — à éviter)
0 === '0'    // false (strict — le bon)
```

---

## 6. Les fonctions

```php
function total(int $a, int $b): int {     // types en option, mais on les met
    return $a + $b;
}

// Fonction fléchée — pour les callbacks
$double = fn($x) => $x * 2;               // JS : const double = x => x * 2
```

Les types (`int`, `string`, `bool`, `: int` pour le retour) sont comme en TS, mais **vérifiés à l'exécution**, pas à la compilation.

---

## 7. Le `null` et ses raccourcis

Mêmes outils qu'en JS moderne :

```php
$nom = $user?->nom;              // nullsafe : ?-> (comme obj?.prop en JS)
$valeur = $prix ?? 0;            // ?? : valeur par défaut si null (identique à JS)
$prix = null;                    // pas de undefined en PHP, juste null
```

Et pour typer "peut être null" : `?int`, `?string` (comme `int | null` en TS).

---

## 8. Les classes — le cœur de Laravel

**Tout Laravel est en classes** : un Model, un Controller, une Notification = une classe. Si tu as fait du NestJS, tu es en terrain connu.

```php
<?php

class Seance {
    public string $name;              // propriété typée + visibilité
    protected ?int $maxParticipants;  // ?int = int OU null

    public function __construct(string $name) {
        $this->name = $name;          // $this = le "this" JS, mais avec ->
    }

    public function estComplete(int $inscrits): bool {
        return $inscrits >= $this->maxParticipants;
    }
}

$s = new Seance('Yoga');       // instanciation, comme en JS
echo $s->name;                 // -> pour accéder aux membres
```

### Le raccourci qu'on voit partout : constructor promotion

Ces deux versions sont identiques — la seconde est celle utilisée dans Laravel :

```php
// version longue
public string $name;
public function __construct(string $name) { $this->name = $name; }

// version courte (property promotion) — déclare + assigne d'un coup
public function __construct(public string $name) {}
```

> C'est l'équivalent des `constructor(private readonly x)` de NestJS/TS. Exactement la même idée.

### Les 4 symboles à reconnaître

| Symbole | Sens | Exemple | En JS |
|---|---|---|---|
| `->` | membre d'une **instance** | `$seance->name` | `obj.name` |
| `::` | membre **statique / de classe** | `Seance::create(...)` | `Class.method()` |
| `$this` | l'instance courante | `$this->name` | `this.name` |
| `?type` | type nullable | `?int` | `int \| null` |

---

## 9. Héritage & interfaces

Comme en TS :

```php
class Seance extends Model { }                               // hérite (extends)
class Notif extends Notification implements ShouldQueue { }  // + respecte un contrat
```

- `extends` = héritage, identique à JS/TS.
- `implements` = « je respecte ce contrat » = les **interfaces** TypeScript.

---

## 10. Les traits — le truc en plus (très utilisé)

Pas d'équivalent direct en JS. Un **trait** est un bloc de méthodes qu'on "colle" dans une classe pour lui ajouter des capacités. Laravel en met partout dans les models :

```php
class Seance extends Model {
    use SoftDeletes, HasRoles;   // ajoute les capacités "soft delete" + "rôles"
}
```

> Pense aux **mixins** ou à un décorateur qui ajoute des méthodes. Quand tu vois `use XxxTrait;` en haut d'une classe, c'est ça.

---

## 11. Namespaces & `use` = les imports

Chaque classe a un **namespace** (son chemin logique), et on l'importe avec `use` — c'est l'`import` de JS.

```php
use App\Models\Seance;          // JS : import { Seance } from '@/models/Seance'
use Illuminate\Support\Facades\Notification;

$s = new Seance();
```

Le `\` sépare les niveaux (comme `/` dans un chemin). `App\Models\Seance` correspond au fichier `app/Models/Seance.php` — ce mapping automatique s'appelle **PSR-4** (comme les alias de chemins `@/` en TS).

---

## 12. On assemble tout : un mini-exemple

Rien de nouveau ici — juste toutes les briques ensemble, comme tu les verras dans un vrai fichier :

```php
<?php

namespace App\Models;                    // 11. namespace

use Illuminate\Database\Eloquent\Model;  // 11. import

class Seance extends Model               // 8+9. classe qui hérite
{
    use SoftDeletes;                     // 10. trait

    public function __construct(
        public string $name,             // 8. property promotion
        public ?int $maxParticipants = null,  // 7. nullable + défaut
    ) {}

    public function estComplete(int $inscrits): bool {   // 6. fonction typée
        return $inscrits >= $this->maxParticipants;      // 8. $this / ->
    }
}
```

Si tu lis ce fichier sans blocage, l'objectif du cours est atteint.

---

## Récap — la carte JS → PHP

- `$` devant chaque variable · `;` en fin de ligne
- Chaînes : `.` pour coller, guillemets doubles = backticks JS
- **`array` unique** = liste **et** objet, accès `['clé']`, paires avec `=>`
- Comparaison : `===` (comme en JS)
- `?->` et `??` : identiques à JS
- Classes : `->` (instance), `::` (statique), `$this`, `?type` (nullable)
- `extends` = héritage, `implements` = interface TS, `use Trait;` = mixin
- `use App\X;` = `import`

## ⚠️ Les 3 pièges qui piquent au début

1. `+` ne colle **pas** les chaînes → utilise `.`
2. On lit un tableau avec `$user['name']`, **pas** `$user->name` ni `$user.name`
3. Le `$` ne s'oublie jamais, même à gauche d'un `=`

➡️ Suite : [Cours 2 — Le modèle mental de Laravel](02-laravel-modele-mental.md)
