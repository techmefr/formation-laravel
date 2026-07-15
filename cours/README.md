# Cours — Formation Laravel StackTim

Parcours d'apprentissage pour un dev qui connaît **JS/TS** et débute en **PHP/Laravel**. Chaque cours est rapide, avec des analogies JS, et prépare le projet « Séances de sport ».

## Parcours

| # | Cours | Ce que tu y gagnes |
|---|---|---|
| 1 | [PHP pour un dev JS](01-php-pour-dev-js.md) | Lire n'importe quel fichier PHP |
| 2 | [Le modèle mental de Laravel](02-laravel-modele-mental.md) | MVC, cycle de requête, artisan, Sail |
| 3 | [Eloquent & les migrations](03-eloquent-migrations.md) | L'ORM et le schéma de base |
| 4 | [Routing, Controllers & validation](04-routing-controllers-validation.md) | Le trajet requête → réponse |
| 5 | [Relations Eloquent & piège N+1](05-relations-eloquent.md) | Liens entre tables, eager loading |
| 6 | [Authentification & permissions](06-auth-permissions.md) | Rôles, permissions Spatie, Policies |
| 7 | [Events, Listeners & Notifications](07-events-listeners-notifications.md) | Async + mails (convention XEFI clé) |
| 8 | [API REST, JWT & lomkit](08-api-rest-jwt-lomkit.md) | Partie II du projet |
| 9 | [Les packages du projet, comment ça marche](09-packages-expliques.md) | Le modèle mental de chaque package du projet |

## Spécial XEFI — pour être opérationnel vite

Focalisé sur **ce que fait XEFI** (conventions notées en revue, packages imposés, recettes du projet). C'est ici qu'il faut passer du temps avant la validation.

| # | Cours | Ce que tu y gagnes |
|---|---|---|
| X1 | [Playbook des conventions](xefi-01-conventions.md) | Les règles non négociables (QCM + revue) |
| X2 | [Packages imposés](xefi-02-packages.md) | spatie, xefi/faker, jwt-auth, lomkit… install + usage |
| X3 | [Recettes du projet « Séances de sport »](xefi-03-recettes-projet.md) | Chaque feature, pas à pas, aux normes XEFI |

## Tutos (pas à pas — tu codes)

Guides de build où tu écris le code toi-même, étape par étape.

| Tuto | Ce que tu construis |
|---|---|
| [L'authentification à la main](tuto-auth-a-la-main.md) | Register / login / logout web (session), sans Breeze/Fortify |

## Référence

- [laravel-cours.md](laravel-cours.md) — le cours complet condensé (les 13 modules d'origine, en un seul document).

## Ensuite

Voir [`../PROGRESSION.md`](../PROGRESSION.md) pour le passage au **projet réel** (scaffolding Laravel + Sail, puis les fonctionnalités « Séances de sport »).
