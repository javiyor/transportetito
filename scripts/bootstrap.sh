#!/usr/bin/env bash
set -euo pipefail

if [ ! -f ".env" ]; then
  cp .env.example .env
  echo "Created .env from .env.example"
fi

if [ ! -d "laravel" ]; then
  mkdir -p laravel
fi

if [ ! -f "laravel/artisan" ]; then
  echo "Laravel app not found in ./laravel"
  echo "Creating Laravel 11 project (this may take a few minutes)..."
  # composer create-project requires an empty directory. Keep repo structure
  # but allow the placeholder .gitkeep.
  if [ -f "laravel/.gitkeep" ]; then
    rm -f "laravel/.gitkeep"
  fi
  if [ -n "$(ls -A laravel 2>/dev/null || true)" ]; then
    echo "ERROR: ./laravel is not empty. Move it aside or empty it and re-run."
    exit 1
  fi
  docker compose run --rm app composer create-project laravel/laravel .
fi

docker compose up -d --build

docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan migrate --force

echo "OK. If DNS points here, open: https://transportetito.5amsoftware.com.ar"
