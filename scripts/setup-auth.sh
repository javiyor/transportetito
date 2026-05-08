#!/usr/bin/env bash
set -euo pipefail

# Installs auth scaffolding with 2FA + roles/permissions.
# Run from repo root on the VPS.

docker compose exec -T app composer require laravel/jetstream
docker compose exec -T app php artisan jetstream:install inertia

# Build frontend assets
docker compose run --rm node

# Roles & permissions
docker compose exec -T app composer require spatie/laravel-permission
docker compose exec -T app php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"

docker compose exec -T app php artisan migrate --force

echo "OK. Auth scaffold + 2FA ready. Configure roles/permissions next."
