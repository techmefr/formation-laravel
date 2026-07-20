# Parcours formation Laravel — StackTim

Projet cible : **application « Séances de sport » XEFI Santé Sport** (voir specs plus bas).
Cours de référence : dossier [`cours/`](cours/) (sommaire dans [cours/README.md](cours/README.md)).

## Phase 0 — Environnement ✅
- [x] WSL Ubuntu + Docker Desktop opérationnels
- [x] Repo `formation-laravel` (remote SSH `techmefr`, branche `main`)
- [x] Projet Laravel 13 + Sail + Laravel Boost scaffoldé
- [x] **Ports Sail uniques** pour cohabiter avec l'autre projet (`platform-api`) : app **8095**, Mailpit **8125**, MinIO console **8901**, + DB/Redis/MinIO décalés (dans `.env`, via `APP_PORT` / `FORWARD_*`). Les ports internes (`DB_PORT`, `REDIS_PORT`, `MAIL_PORT`) restent standards.

## Phase 1 — Comprendre ✅ (cours dans `cours/`)
- [x] **PHP pour un dev JS** — syntaxe, tableaux, classes, traits, typage
- [x] Modèle mental Laravel — MVC, cycle de requête (≈ Express : middleware + `$next`), artisan, Sail
- [x] Eloquent & migrations — analogie Prisma (+ FAQ : `timestamps`/`softDeletes`, `delete`/`forceDelete`, préfixe `0001_01_01`, attributs `#[Fillable]`)
- [x] Routing + Controllers + validation (+ couche Service, politique mot de passe, convention status+message)
- [x] Relations Eloquent (belongsTo / hasMany / belongsToMany)
- [x] Auth & permissions (spatie) — + « en vrai chez StackTim » : lomkit access-control
- [x] Events / Listeners / Notifications (convention XEFI : pas d'Observer)
- [x] API REST + JWT + lomkit (Partie II) — + auth réelle Azure/JWT cookie
- [x] Les packages du projet expliqués (Cours 9)

## Phase 2 — Construire le projet
Ordre réel, dans lequel on avance :

1. **Environnement** : Docker + Sail, **ports uniques** — ✅
2. **Packages imposés** installés (spatie permission/medialibrary/activitylog, tymon/jwt, lomkit) — ✅
3. **Rôles & permissions** (spatie) : 3 rôles + permissions, seeder idempotent, 3 users de test — ✅
4. **Auth à la main** : register / login / logout / reset, **couche Service** (comme le front), **politique mot de passe** (12 / maj / min / chiffre / spécial), pages Blade + dashboard — ✅ *(version web/session : étape d'apprentissage, voir point 10)*
5. **CRUD séances** + permissions par rôle (Policy « les siennes ») — ✅
6. **Upload fichiers** (spatie/laravel-medialibrary, MinIO/RustFS S3) — ✅
7. **Inscription / désinscription** (bouton masqué si limite atteinte) — ✅
8. **Notifications mail** (Mailpit) à la création/suppression — via Listener sur event Eloquent — ✅
9. **Seeding réaliste** (xefi/faker-php) — ✅
10. **Partie II — bascule API (la vraie façon StackTim)** : JWT en cookie (guard `api`) + `lomkit/laravel-rest-api` + `lomkit/laravel-access-control` (Controls/Perimeters). L'auth session (point 4) est la marche pour comprendre ce que JWT + lomkit automatisent.
    - [x] JWT (guard `api`, login/me/refresh/logout) — branche `feat/partie-2`, [cours 13](cours/13-jwt-implementation.md)
    - [x] lomkit/laravel-rest-api sur les séances (search/mutate, relations coach/place/participants) — branche `feat/partie-2`. Deux pièges rencontrés : le cache d'autorisation lomkit sérialise mal `Illuminate\Auth\Access\Response` sur `CACHE_STORE=database` (désactivé dans `config/rest.php`), et spatie/permission résout les rôles par guard — `User::guardName()` fixe `web` pour que `$user->can(...)` marche aussi via le guard `api`.
    - [ ] lomkit/laravel-access-control (Controls/Perimeters)

> Point 1 à 9 : livrés dans la **PR #1** (`feat/partie-1` → `develop`). Le point 10 (Partie II) avance sur `feat/partie-2`.

> 🔴 Rappel « en vrai » (observé sur `platform-api`) : en production l'auth est **JWT (cookie) via Azure OAuth**, et l'autorisation passe par **lomkit access-control** (Controls + Perimeters + permissions spatie), pas par des Policies écrites à la main. La Partie I web/session reste l'étape pédagogique.

### Champs d'une séance
`name` (string, requis) · `coach` (relation, requis) · `started_at` (datetime, requis) · `max_participants` (int) · `files` (media)

### Permissions par rôle
| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| Admin | toutes | toutes | toutes |
| Coach | les siennes | les siennes | non |
| Collaborateur | non | non | non |
