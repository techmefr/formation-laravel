# Makefile — projet Séances de sport (Laravel Sail)
# Usage : `make <cible>`. Tape juste `make` pour l'aide.

SAIL := ./vendor/bin/sail

.DEFAULT_GOAL := help

## —— Environnement ———————————————————————————————————————
.PHONY: up
up: ## Démarre les conteneurs (arrière-plan)
	$(SAIL) up -d

.PHONY: down
down: ## Arrête les conteneurs
	$(SAIL) down

.PHONY: restart
restart: down up ## Redémarre les conteneurs

.PHONY: ps
ps: ## Liste les conteneurs
	$(SAIL) ps

.PHONY: logs
logs: ## Affiche les logs (Ctrl+C pour quitter)
	$(SAIL) logs -f

.PHONY: shell
shell: ## Ouvre un shell dans le conteneur applicatif
	$(SAIL) shell

## —— Application ——————————————————————————————————————————
.PHONY: install
install: ## Installe les dépendances + clé d'app (après un clone)
	$(SAIL) composer install
	$(SAIL) artisan key:generate

.PHONY: tinker
tinker: ## REPL interactif sur l'appli
	$(SAIL) artisan tinker

.PHONY: cache-clear
cache-clear: ## Vide tous les caches
	$(SAIL) artisan optimize:clear

## —— Base de données ——————————————————————————————————————
.PHONY: migrate
migrate: ## Applique les migrations
	$(SAIL) artisan migrate

.PHONY: fresh
fresh: ## Recrée la base + seeders (⚠️ efface les données)
	$(SAIL) artisan migrate:fresh --seed

.PHONY: seed
seed: ## Lance les seeders
	$(SAIL) artisan db:seed

## —— Qualité (conventions XEFI) ———————————————————————————
.PHONY: test
test: ## Lance les tests
	$(SAIL) artisan test

.PHONY: pint
pint: ## Formate le code (Laravel Pint)
	$(SAIL) pint

.PHONY: stan
stan: ## Analyse statique (Larastan, niveau >= 5)
	$(SAIL) php ./vendor/bin/phpstan analyse

.PHONY: check
check: pint stan test ## Pint + Larastan + tests (à passer avant commit)

## —— Aide —————————————————————————————————————————————————
.PHONY: help
help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'
