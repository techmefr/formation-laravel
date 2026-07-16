# Formation Laravel StackTim — « Séances de sport »

Projet fil rouge de la formation Laravel StackTim (XEFI) : une application de gestion de **séances de sport** où des collaborateurs s'inscrivent aux séances animées par des coachs.

Le repo sert deux buts :
- **apprendre** Laravel en partant d'un profil JS/TS (voir le dossier [`cours/`](cours/README.md)) ;
- **construire** l'application pas à pas, aux conventions XEFI.

## Stack

- **PHP 8.4** · **Laravel 13**
- **Laravel Sail** (Docker) pour l'environnement local
- **MySQL** en base
- **Blade** + sessions pour l'auth (Partie I) — JWT / lomkit prévus en Partie II
- **spatie/laravel-permission** (rôles & permissions), **xefi/faker** (données de seed FR)
- Qualité : **Pint** (format), **Larastan** niveau 5 (analyse statique), **PHPUnit** (tests)

## Domaine

Quatre rôles :

| Rôle | Peut |
|---|---|
| `admin` | tout : créer / modifier / annuler / supprimer toute séance, gérer les participants |
| `manager` | admin des coachs : modifier / annuler / supprimer toute séance, gérer les participants |
| `coach` | créer ses séances, et modifier / annuler / supprimer **les siennes** |
| `collaborator` | consulter et **s'inscrire / se désinscrire** |

Règles clés :
- **S'inscrire n'est pas modifier** : un collaborateur s'inscrit à une séance sans avoir le droit de la modifier (routes et autorisations séparées).
- admin / manager / coach (sur ses séances) peuvent inscrire ou désinscrire **n'importe qui**.
- **File d'attente** : au-delà de `max_participants`, l'inscription passe en `waitlist` ; à la désinscription d'un inscrit, le premier de la file est promu automatiquement.
- **Annuler ≠ supprimer** : une annulation pose un `cancelled_at` (la séance reste visible, marquée annulée).

## Démarrer

Prérequis : Docker + Docker Compose.

```bash
# 1. Dépendances + clé d'application (après un clone)
make install

# 2. Démarrer les conteneurs
make up

# 3. Base de données + données de démo (rôles, users, séances, inscriptions)
make fresh
```

L'application est ensuite disponible sur le port défini dans `.env` (`APP_PORT`).

### Comptes de démo (mot de passe `password`)

| Email | Rôle |
|---|---|
| `admin@example.com` | admin |
| `manager@example.com` | manager |
| `coach@example.com` | coach |
| `collab@example.com` | collaborator |

## Commandes utiles (`make help`)

| Commande | Effet |
|---|---|
| `make up` / `make down` | démarre / arrête les conteneurs |
| `make fresh` | recrée la base + seeders (⚠️ efface les données) |
| `make seed` | relance les seeders |
| `make migrate` | applique les migrations |
| `make tinker` | REPL sur l'application |
| `make test` | lance les tests |
| `make pint` | formate le code |
| `make stan` | analyse statique (Larastan ≥ 5) |
| `make check` | **Pint + Larastan + tests** — à passer avant chaque commit |

## Structure du back

```
app/
  Http/Controllers/
    Auth/                     # register / login / logout / reset (à la main, sessions)
    InscriptionController     # s'inscrire / se désinscrire soi-même
    ParticipantController     # inscrire / désinscrire autrui (Policy manageParticipants)
  Services/
    AuthService               # logique d'auth
    InscriptionService        # inscription + file d'attente (register / unregister / promote)
  Models/                     # Seance, User, Place, Media
  Policies/SeancePolicy       # « le coach ne gère que ses séances »
database/
  migrations/                 # users, séances, places, pivot seance_user, permissions spatie
  seeders/                    # rôles+permissions, users, places, séances + inscriptions
  factories/                  # xefi/faker (locale fr_FR)
  schema.dbml                 # schéma pour dbdiagram.io
routes/web.php                # auth + inscription + participants
```

## Apprentissage

- [`cours/README.md`](cours/README.md) — le parcours complet (PHP pour dev JS → Eloquent → auth/permissions → API/JWT) + les recettes XEFI et les tutos de build.
- [`PROGRESSION.md`](PROGRESSION.md) — le suivi d'avancement du projet.

## État d'avancement

- ✅ Authentification web (register / login / logout / reset), à la main
- ✅ Rôles & permissions (spatie), séances + places, seeders
- ✅ Inscription / désinscription + file d'attente (Service, controllers, Policy)
- 🚧 CRUD des séances côté back (SeanceController / Service / Form Requests / route `cancel`) et vues Blade
- 🚧 Front : page calendrier (semaine, inscription, filtre par agence)
- 🔜 Notifications · Partie II (API REST / JWT / lomkit)
