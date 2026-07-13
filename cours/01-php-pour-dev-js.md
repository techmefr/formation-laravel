# Cours 1 — Juste assez de PHP pour lire du Laravel

> Pour un dev qui connaît déjà JS/TS. On ne fait que le nécessaire pour lire/écrire du Laravel, en JS ↔ PHP côte à côte.

## 1. Comment PHP tourne

PHP est un langage **serveur** : un fichier `.php` est exécuté à chaque requête HTTP, il produit une réponse, puis **tout est oublié** — pas d'état en mémoire entre deux requêtes (contrairement à un serveur Node qui reste allumé). C'est *stateless* par nature, d'où l'importance de la base de données et du cache.

Chaque instruction se termine par `;`, les variables commencent par **`$`**.

```php
<?php
$nom = "Gaetan";
echo "Bonjour $nom";   // les variables s'interpolent dans les guillemets doubles
```

## 2. Variables & types

Pas de `let`/`const` : juste `$maVar`. Typage dynamique comme JS, mais PHP 8.4 permet de **typer** presque partout (et Laravel en abuse, tant mieux).

```php
$age = 30;              // int
$prix = 19.99;          // float
$actif = true;          // bool
$nom = "yoga";          // string
$rien = null;           // null
```

## 3. Strings : le piège du `.`

La concaténation se fait avec **`.`** — `+` c'est *uniquement* l'addition en PHP.

```php
$a = "Séance " . "yoga";        // "Séance yoga"
$msg = "Coach : {$coach->nom}"; // interpolation, {} pour les expressions
```

## 4. Les tableaux — LE concept clé

En PHP il n'y a qu'**un seul** type `array`, qui sert à la fois de **liste** (array JS) ET d'**objet/map** (`{}` / `Map`). Central, car Laravel renvoie des `array` associatifs partout (config, règles de validation, JSON).

```php
// Liste (clés numériques auto)
$roles = ['admin', 'coach', 'collaborator'];
echo $roles[0];          // 'admin'

// Associatif = l'équivalent d'un objet JS { }
$user = [
    'name'  => 'Gaetan',   // => au lieu de :
    'email' => 'g@xefi.fr',
];
echo $user['name'];        // accès avec ['clé'], PAS .name
```

| JavaScript | PHP |
|---|---|
| `const a = [1, 2, 3]` | `$a = [1, 2, 3];` |
| `const o = { name: 'x' }` | `$o = ['name' => 'x'];` |
| `o.name` | `$o['name']` |
| `a.map(fn)` | `array_map($fn, $a)` |
| `a.filter(fn)` | `array_filter($a, $fn)` |

> 💡 En Laravel tu utiliseras surtout les **Collections** (`collect($a)->map()->filter()`), qui ramènent la syntaxe fluide et chaînée de JS. Mais le socle reste l'`array`.

## 5. Fonctions

```php
function total(int $a, int $b): int {   // types optionnels mais recommandés
    return $a + $b;
}

// Fonction fléchée — pour les callbacks, comme en JS
$double = fn($x) => $x * 2;
```

## 6. Les classes — la partie qui compte vraiment

**Laravel, c'est de la POO du début à la fin.** Un Model, un Controller, une Notification = une classe.

```php
<?php

class Seance {
    public string $name;
    protected ?int $maxParticipants;   // ?int = int OU null

    public function __construct(string $name) {
        $this->name = $name;           // $this = "this", mais avec ->
    }

    public function estComplete(int $inscrits): bool {
        return $inscrits >= $this->maxParticipants;
    }
}

$s = new Seance('Yoga');       // instanciation
echo $s->name;                 // -> pour accéder à un membre d'instance
```

Les 4 opérateurs à retenir :

| Symbole | Sens | Exemple |
|---|---|---|
| `->` | accès **instance** (méthode/propriété d'un objet) | `$seance->name` |
| `::` | accès **statique / de classe** | `Seance::create(...)` |
| `$this` | l'instance courante (le `this` JS) | `$this->name` |
| `?type` | type **nullable** | `?int`, `?string` |

Héritage & interfaces (dans chaque fichier Laravel) :

```php
class Seance extends Model { }                              // hérite de Model
class Notif extends Notification implements ShouldQueue { } // + contrat/interface
```

`extends` = héritage (comme JS). `implements` = « je respecte ce contrat » (proche des interfaces TypeScript).

## 7. Namespaces & `use` — les imports

Chaque classe vit dans un **namespace** (son chemin logique). `use` importe une classe, comme `import` en JS.

```php
use App\Models\Seance;              // ≈ import { Seance } from '@/models/Seance'
use Illuminate\Support\Facades\Notification;

$s = new Seance();
```

Le `\` sépare les niveaux (comme `/`). `App\Models\Seance` ↔ le fichier `app/Models/Seance.php` : la convention **PSR-4** fait ce mapping automatiquement (comme les alias de chemins en TS).

---

## Récap mental JS → PHP

- `$` devant les variables · `;` à la fin · `.` pour concaténer (jamais `+`)
- `array` unique = liste **et** objet, accès par `['clé']`, flèche `=>` pour les paires
- POO partout : `->` (instance), `::` (statique), `$this`, `?` (nullable)
- `use` = `import`, namespaces mappés sur l'arborescence des fichiers

C'est ~80 % de ce que tu liras dans du code Laravel. Le reste (Eloquent, façades, helpers) s'apprend en contexte.

➡️ Suite : [Cours 2 — Le modèle mental de Laravel](02-laravel-modele-mental.md)
