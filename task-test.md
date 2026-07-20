# Plan de tests — Séances de sport (doctrine test-casebook adaptée Laravel)

Feature tests HTTP, comportement côté user, matrice de permissions (autorisé + refusé), exhaustif, zéro commentaire. PHPUnit + RefreshDatabase. Chaque test pilote le rôle/permission.

Adaptation doctrine : `data-test-*` non applicable (pas de DOM en test HTTP) ; on conserve matrice de permissions, exhaustivité, no-comments, plan-first, couverture élevée.

Suite : 56 tests, 123 assertions, verte.

## Bloc 1 — Accès & redirections (AccessTest)
- [x] `/` invité → redirige login
- [x] `/` connecté → redirige /seances
- [x] page protégée invité → redirige login
- [x] login OK → /seances ; mauvais mot de passe → erreur ; validation email requise
- [x] register OK → /seances + rôle collaborator ; politique mot de passe refusée
- [x] logout → invité

## Bloc 2 — CRUD séance (SeanceCrudTest, matrice de permissions)
Actions : create / update / cancel / delete. Personas pilotés : admin, manager, coach (sienne), coach (autre), collaborator.
- [x] create : admin/manager/coach OK · collaborator refusé (403) · invité → login
- [x] update : admin/manager OK · coach sur la sienne OK · coach sur autre refusé · collaborator refusé
- [x] cancel : idem update + pose `cancelled_at`
- [x] delete : idem update + soft delete
- [x] coach qui crée → `coach_id` forcé sur lui-même
- [x] validation : name requis, ended_at après started_at, max_participants ≥ 1

## Bloc 3 — Inscription & participants (InscriptionTest)
- [x] s'inscrire soi-même OK (registered) ; déjà inscrit → no-op
- [x] séance pleine → waitlist ; désinscription d'un registered → promotion du 1er en file
- [x] conflit horaire → inscription bloquée
- [x] invité → login
- [x] gérer autrui : admin/manager/coach-sienne OK · collaborator/coach-autre refusé · retrait d'un participant

## Bloc 4 — Calendrier (CalendarEventsTest)
- [x] collaborator : externe + son agence seulement
- [x] coach : ses cours seulement
- [x] admin : tout
- [x] filtre `agency` + filtre `mine`
- [x] invité → 401

## Bloc 5 — Notifications (SeanceNotificationTest, Notification::fake)
- [x] création → notif au coach
- [x] annulation → notif à chaque participant

## Bloc 6 — Upload (SeanceUploadTest, Storage::fake)
- [x] création avec fichier → média attaché à la collection `files`
- [x] fichier trop lourd → erreur de validation, séance non créée
