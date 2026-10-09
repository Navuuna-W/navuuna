#!/usr/bin/env bash
# First-time start of the local stack (work pack K-08): settings, images, tables, seed data.
# Run from anywhere: infra/local/setup.sh. Safe to run again — it keeps an existing .env,
# migrations skip what is already there and the seed skips rows it already loaded.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="$REPO_ROOT/infra/local/.env"
STUB_SEED="$REPO_ROOT/tests/fixtures/local-seed.sql"

compose() {
    docker compose -f "$REPO_ROOT/infra/docker-compose.yml" "$@"
}

# Each laptop gets its own random APP_KEY, so no key is ever committed.
create_env_file() {
    if [ -f "$ENV_FILE" ]; then
        echo "Keeping existing $ENV_FILE"
        return
    fi
    local app_key="base64:$(openssl rand -base64 32)"
    sed "s|^APP_KEY=$|APP_KEY=$app_key|" "$ENV_FILE.example" > "$ENV_FILE"
    echo "Created $ENV_FILE"
}

# Devyan's 500-entity fixture once it lands (data/fixtures/*.sql); the stub until then.
load_seed_data() {
    local fixture_files=("$REPO_ROOT"/data/fixtures/*.sql)
    if [ ! -e "${fixture_files[0]}" ]; then
        fixture_files=("$STUB_SEED")
    fi
    for fixture_file in "${fixture_files[@]}"; do
        echo "Loading $fixture_file"
        compose exec -T postgis psql --username=navuuna --dbname=navuuna \
            --set=ON_ERROR_STOP=1 --quiet < "$fixture_file"
    done
}

create_env_file
compose build
compose up -d --wait postgis redis
compose run --rm api php artisan migrate --force
load_seed_data
compose up -d
echo "Running: API http://localhost:8000/up · web http://localhost:5173 · MinIO http://localhost:9001"
