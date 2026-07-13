# Parcours formation Laravel — StackTim

Projet cible : **application « Séances de sport » XEFI Santé Sport** (voir specs plus bas).
Cours de référence : [cours/laravel-cours.md](cours/laravel-cours.md).

## Phase 0 — Environnement ✅
- [x] WSL Ubuntu + Docker Desktop opérationnels
- [x] Repo `formation-laravel` (remote SSH `techmefr`, branche `main`)
- [x] Cours sauvegardé

## Phase 1 — Comprendre (en cours)
- [ ] **PHP pour un dev JS** — syntaxe, variables, tableaux, fonctions, classes, typage
- [ ] Modèle mental Laravel — MVC, cycle de requête, artisan, Sail
- [ ] Eloquent & migrations (l'ORM) — analogie Prisma
- [ ] Routing + Controllers + Form Requests (validation)
- [ ] Relations Eloquent (belongsTo / hasMany / belongsToMany)
- [ ] Auth & permissions (spatie/laravel-permission)
- [ ] Events / Listeners / Notifications (convention XEFI : pas d'Observer)
- [ ] API REST + JWT + lomkit (Partie II)

## Phase 2 — Construire le projet
Suivre les étapes StackTim :
1. Scaffolder Laravel + Sail (`curl -s https://laravel.build/... | bash`, `sail up -d`)
2. Auth + rôles (`admin`, `coach`, `collaborator`) via spatie/laravel-permission
3. CRUD séances + permissions par rôle
4. Upload fichiers (spatie/laravel-medialibrary, MinIO/RustFS S3)
5. Inscription / désinscription (bouton masqué si limite atteinte)
6. Notifications mail (Mailpit) à la création/suppression — via Listener sur event Eloquent
7. Seeding réaliste (xefi/faker-php)
8. **Partie II** : API REST sécurisée JWT (tymon/jwt-auth) + industrialisation (lomkit/laravel-rest-api)

### Champs d'une séance
`name` (string, requis) · `coach` (relation, requis) · `started_at` (datetime, requis) · `max_participants` (int) · `files` (media)

### Permissions par rôle
| Rôle | Créer | Modifier | Supprimer |
|---|---|---|---|
| Admin | toutes | toutes | toutes |
| Coach | les siennes | les siennes | non |
| Collaborateur | non | non | non |
