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
| 10 | [Tester une app Laravel](10-tests.md) | Feature tests, matrice de permissions, fakes (ta doctrine côté back) |
| 11 | [L'authentification à la main (pas à pas)](11-auth-a-la-main.md) | Register / login / logout web (session), sans Breeze/Fortify |
| 12 | [Le CRUD des séances (pas à pas)](12-seances-crud.md) | Model + Policy + Form Request + controller/service + vues, permissions par rôle |

Les cours 1 à 12 couvrent la **Partie I** du projet : l'app web classique (session, Blade, Policies écrites à la main). Le cours 8 introduit la théorie de la Partie II ; sa mise en pratique est dans la section dédiée ci-dessous.

## Partie II — API (JWT + lomkit)

Bascule de l'app web vers une **API REST stateless**, en réutilisant les mêmes Policies et la même base d'utilisateurs que la Partie I. Théorie dans le [Cours 8](08-api-rest-jwt-lomkit.md) ; ces deux cours sont la mise en pratique, pas à pas, sur le vrai code du projet.

| # | Cours | Ce que tu y gagnes |
|---|---|---|
| 13 | [JWT à la main (pas à pas)](13-jwt-implementation.md) | Guard `api`, login/me/refresh/logout, l'ordre JWT → endpoints |
| 14 | [lomkit/laravel-rest-api en pratique (pas à pas)](14-lomkit-rest-api.md) | CRUD séances (search/mutate), relations, Actions métier, 4 pièges réels |
| 15 | [lomkit/laravel-access-control (Controls/Perimeters)](15-lomkit-access-control.md) | Remplacer les Policies à la main par Controls/Perimeters, filtrage de liste via `controlled()` |
| 16 | [Tester la Partie II (JWT, lomkit, Access Control)](16-tests-api.md) | Auth par token, format search/mutate/destroy/actions, tester un Perimeter, events |

## Spécial XEFI — pour être opérationnel vite

Focalisé sur **ce que fait XEFI** (conventions notées en revue, packages imposés, recettes du projet). C'est ici qu'il faut passer du temps avant la validation.

| # | Cours | Ce que tu y gagnes |
|---|---|---|
| X1 | [Playbook des conventions](xefi-01-conventions.md) | Les règles non négociables (QCM + revue) |
| X2 | [Packages imposés](xefi-02-packages.md) | spatie, xefi/faker, jwt-auth, lomkit… install + usage |
| X3 | [Recettes du projet « Séances de sport »](xefi-03-recettes-projet.md) | Chaque feature, pas à pas, aux normes XEFI |

> Les cours 11, 12, 13, 14 et 15 sont des **cours pratiques** : tu écris le code toi-même, étape par étape.

## Référence

- [laravel-cours.md](laravel-cours.md) — le cours complet condensé (les 13 modules d'origine, en un seul document).

## Ensuite

Voir [`../PROGRESSION.md`](../PROGRESSION.md) pour le passage au **projet réel** (scaffolding Laravel + Sail, puis les fonctionnalités « Séances de sport »).
