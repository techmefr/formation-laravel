# Cours 2 — Le modèle mental de Laravel

> Comment les pièces s'emboîtent. Si tu as déjà fait du NestJS ou du Nuxt, l'esprit « framework à conventions fortes » te sera familier.

## 1. Laravel en une phrase

Un **framework full-stack PHP** qui te fournit toute la plomberie web (routing, base, auth, mails, queues) pour que tu écrives surtout ta **logique métier**. Philosophie : **convention plutôt que configuration**. Nomme bien → ça se câble tout seul.

## 2. MVC : qui fait quoi

```
Requête HTTP
   │
   ▼
[Route]  →  [Controller]  →  [Model / Eloquent]  →  Base de données
                │
                ▼
           [Réponse]  (JSON pour une API, ou vue Blade pour du web)
```

- **Model** — les données + la logique associée (une `Seance`, un `User`). Parle à la base via Eloquent.
- **Controller** — le chef d'orchestre : reçoit la requête, appelle les models, renvoie une réponse. Reste **mince**.
- **View** — l'affichage HTML (Blade). Peu utilisé dans ton projet (Partie II = API JSON).

| Ton monde | Laravel |
|---|---|
| Route handler Nuxt/Express | Controller + fichier `routes/` |
| Prisma/Drizzle | Model Eloquent |
| Composant + template | Vue Blade |
| Middleware Express | Middleware (quasi identique) |

## 3. Le cycle de vie d'une requête

Toute requête traverse **le même pipeline** — c'est LA chose à comprendre, car ça dit où ton code s'insère :

```
Service Providers → Middleware → Routing → Controller → Response
```

1. **Service Providers** — le boot. Laravel enregistre et démarre tous les services (base, auth, packages). **Point central de config.**
2. **Middleware** — les filtres autour de la requête : connecté ? (`auth`), a la permission ?, CORS, throttling. Peut stopper la requête avant ton code.
3. **Routing** — associe `GET /seances` à une route → un controller.
4. **Controller** — ton code s'exécute.
5. **Response** — repart en sens inverse à travers les middleware jusqu'au client.

> ⚠️ **Piège de QCM** : on inverse souvent Middleware et Service Providers. Non — sans les providers bootstrapés, rien n'existe. **Providers d'abord.**

## 4. Artisan — le CLI (ton `npm run`)

Chaque projet a un fichier `artisan`. Il génère du code, gère la base, inspecte l'appli.

```bash
php artisan make:model Seance -mfsc  # model + migration + factory + seeder + controller
php artisan migrate                  # applique les migrations
php artisan route:list               # liste toutes les routes
php artisan tinker                   # REPL interactif sur ton appli (comme node)
```

`make:*` génère des fichiers pré-remplis : tu passes ton temps à créer des classes via artisan plutôt qu'à la main.

## 5. Sail — Docker sans la douleur

Chez XEFI rien n'est installé sur la machine : tout tourne dans **Docker**. **Sail** est le script qui pilote ces conteneurs.

- **Sail** gère la *boîte* (l'environnement Docker : PHP, MySQL, Redis, Mailpit).
- **Artisan** gère ce qu'il y a *dans* la boîte (ton appli Laravel).

```bash
sail up -d              # démarre les conteneurs en arrière-plan
sail artisan migrate    # une commande artisan, mais DANS le conteneur
sail composer require … # composer dans le conteneur
sail down               # arrête tout
```

Comme PHP n'est pas installé sur ta machine, tu tapes **toujours** `sail artisan …`, jamais `php artisan …` en direct.

## 6. La structure d'un projet

| Emplacement | Contenu |
|---|---|
| `app/` | Ton code : `Models/`, `Http/Controllers/`, `Http/Requests/`, `Listeners/`, `Notifications/` |
| `routes/` | `web.php` (pages) et `api.php` (API, préfixé `/api`) |
| `database/` | `migrations/`, `factories/`, `seeders/` |
| `config/` | Config PHP qui lit le `.env` |
| `.env` | Secrets par environnement. **Jamais commité.** |
| `composer.json` | Dépendances PHP (≈ `package.json`) |
| `vendor/` | Dépendances installées (≈ `node_modules`, jamais commité) |

## 7. Composer — le npm de PHP

```bash
sail composer require spatie/laravel-permission   # ajoute une dépendance
sail composer install                             # installe tout composer.json → vendor/
```

`composer.json` = `package.json`. `vendor/` = `node_modules/`. `composer.json` **ne compile rien** et **ne gère pas les routes** — il déclare juste les dépendances.

---

## À retenir

- Tout passe par le pipeline **Providers → Middleware → Routing → Controller → Response**.
- **artisan** = ton CLI qui génère du code (`make:*`) et pilote la base.
- **Sail** = la boîte Docker ; **artisan** = dedans. Toujours `sail artisan`.
- **Composer** = npm ; `.env` = secrets non commités ; conventions de nommage partout.

➡️ Suite : [Cours 3 — Eloquent & les migrations](03-eloquent-migrations.md)
