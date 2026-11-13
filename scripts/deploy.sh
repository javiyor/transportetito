#!/usr/bin/env bash
set -Eeuo pipefail

root="$(git rev-parse --show-toplevel)"
cd "$root"

branch="$(git branch --show-current)"
if [[ "$branch" != "main" ]]; then
    echo "ERROR: deploy must run from main (current branch: $branch)." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: working tree is not clean; commit or preserve local changes before deploying." >&2
    git status --short
    exit 1
fi

maintenance=false
code_updated=false
schema_ready=false
restore_app() {
    if [[ "$maintenance" == true ]]; then
        if [[ "$code_updated" == false || "$schema_ready" == true ]]; then
            docker compose exec -T app php artisan up || true
        else
            echo "ERROR: code updated but migrations did not complete; app remains in maintenance mode." >&2
            echo "Resolve the migration issue, then run: docker compose exec -T app php artisan up" >&2
        fi
    fi
}
trap restore_app EXIT

docker compose up -d postgres redis minio app
docker compose exec -T app php artisan down --retry=60
maintenance=true

git pull --ff-only origin main
code_updated=true
docker compose run --rm node
docker compose exec -T app php artisan migrate --force
schema_ready=true
docker compose exec -T app php artisan optimize:clear
docker compose restart app queue scheduler
docker compose exec -T app php artisan up
maintenance=false
trap - EXIT

echo "Deploy finished successfully."
