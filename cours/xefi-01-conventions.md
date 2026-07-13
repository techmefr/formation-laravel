# XEFI 01 — Le playbook des conventions

> Les règles **non négociables**, notées en revue par ton Lead et ton référent, et testées au QCM. À connaître par cœur. Chaque règle = le **quoi**, le **pourquoi**, et le **do/don't**.

## Le fil rouge (à retenir avant tout)

> **Rendre les effets de bord explicites et testables.**

Toutes les règles ci-dessous découlent de ce principe. Un comportement caché (Observer, `boot()` magique) = mauvais. Un comportement déclaré à un endroit lisible et unitairement testable = bon. Si tu hésites sur une convention, reviens à cette phrase.

---

## 1. 🔴 Réactivité au cycle de vie d'un model → Listener, jamais Observer

**Règle.** Interdits : les **Observers**, la méthode `boot()` dans les models, un dossier `app/Events/` avec des classes Event custom. Toute réaction à un `created`/`updated`/`deleted`… passe par un **Listener** branché sur l'**événement Eloquent natif**, déclaré dans l'`EventServiceProvider`.

**Pourquoi.** Un Observer est résolu implicitement : on ne voit nulle part qu'il tourne. Un Listener déclaré dans l'`EventServiceProvider` rend le branchement visible et testable.

```php
// ❌ INTERDIT — Observer
class SeanceObserver {
    public function created(Seance $seance) { /* ... */ }
}

// ❌ INTERDIT — boot() dans le model
class Seance extends Model {
    protected static function boot() {
        parent::boot();
        static::created(fn ($s) => /* ... */);
    }
}
```

```php
// ✅ ATTENDU — Listener sur événement Eloquent natif
// app/Providers/EventServiceProvider.php
protected $listen = [
    'eloquent.created: ' . Seance::class => [NotifySeanceCreated::class],
    'eloquent.deleted: ' . Seance::class => [NotifySeanceDeleted::class],
];
```

C'est **le** point sur lequel ton référent te reprendra. Retiens-le en priorité.

---

## 2. 🔴 Autorisation → on raisonne en permissions, pas en rôles

**Règle.** Dans le code métier, teste une **permission** (`can('delete seances')`), jamais un rôle (`hasRole('admin')`). Les rôles ne servent qu'à regrouper des permissions.

**Pourquoi.** Souplesse : si demain un nouveau rôle doit pouvoir supprimer, tu ajoutes la permission au rôle, sans toucher une seule ligne de logique.

```php
if ($user->can('delete seances')) { … }   // ✅
if ($user->hasRole('admin'))       { … }   // ❌ dans la logique métier
```

Package imposé : `spatie/laravel-permission`. Détails dans [XEFI 02](xefi-02-packages.md).

---

## 3. 🔴 Notifications → via Notification, déclenchée par un Listener

**Règle.** Les mails partent via une classe **Notification** (`implements ShouldQueue`), **jamais** via un Mailable brut envoyé « en dur ». Et le déclenchement vient d'un **Listener sur événement Eloquent** (règle 1), jamais d'un Observer ou du controller.

```php
// ✅ dans le Listener
Notification::send($destinataires, new SeanceCreatedNotification($seance));
```

Mails en local : **Mailpit** (intégré à Sail, UI sur `:8025`), pas de SMTP réel.

---

## 4. 🔴 Soft delete → aussi Prunable

**Règle.** Un model en `SoftDeletes` doit implémenter `Prunable` : prévoir le nettoyage périodique des lignes réellement obsolètes.

**Pourquoi.** Sinon la table gonfle indéfiniment (les `deleted_at` s'accumulent sans jamais être purgés).

---

## 5. 🔴 Seeding → `xefi/faker-php`, pas `fakerphp/faker`

**Règle.** Le seeding utilise le package maison `xefi/faker-php` (données réalistes : séances, users, rôles). Objectif : une appli **entièrement testable** via un seeding complet.

---

## 6. 🔴 Config → `env()` uniquement dans `config/`

**Règle.** Tu n'appelles `env()` que dans les fichiers `config/*.php`. Partout ailleurs : `config('services.microsoft.client_id')`.

**Pourquoi.** En production on lance `config:cache` : Laravel fusionne la config en un fichier et **ne relit plus le `.env`**. Un `env()` dans un controller renverra alors `null`.

```php
// config/services.php
'microsoft' => ['client_id' => env('MS_CLIENT_ID')],   // ✅ env() ici

// n'importe où ailleurs
$id = config('services.microsoft.client_id');           // ✅ config() partout ailleurs
$id = env('MS_CLIENT_ID');                               // ❌ cassé après config:cache
```

---

## 7. 🔴 Qualité de code → Larastan niv. 5 + Pint

**Règle.**
- **Larastan** (analyse statique) : niveau **5 minimum**.
- **Laravel Pint** (formatage) : un `pint.json` à la racine, code formaté avant commit.

```bash
sail php ./vendor/bin/phpstan analyse    # Larastan (niveau défini dans phpstan.neon)
sail pint                                # formate tout le code
```

---

## 8. 🔴 Outils de debug selon l'environnement

| Outil | Où | Rôle |
|---|---|---|
| **Telescope** | dev / staging **uniquement** | inspection requêtes, jobs, mails… |
| **Pulse** | production | métriques de santé/perf |
| **Horizon** | là où il y a des queues Redis | dashboard des queues |

Ne jamais laisser Telescope actif en prod.

---

## Récap — la check-list de revue

- [ ] Aucun Observer / `boot()` model / `app/Events/` custom → **Listeners** dans l'`EventServiceProvider`
- [ ] Logique d'accès en **permissions** (`can`), pas en rôles (`hasRole`)
- [ ] Mails via **Notification** `ShouldQueue`, déclenchés par un Listener
- [ ] Models `SoftDeletes` → aussi **`Prunable`**
- [ ] Seeding avec **`xefi/faker-php`**
- [ ] `env()` seulement dans `config/`, sinon `config()`
- [ ] **Larastan ≥ 5** vert, code **Pint** formaté
- [ ] **Telescope** hors prod, **Pulse** en prod

➡️ Suite : [XEFI 02 — Les packages imposés](xefi-02-packages.md)
