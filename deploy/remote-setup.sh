#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
	echo "Missing .env — copy from deploy/env.production.example and configure." >&2
	exit 1
fi

COMPOSE=(docker compose -f docker-compose.production.yml)
PHP_BUILD_HASH="$(find docker/php -type f | sort | xargs sha256sum | sha256sum | cut -d" " -f1)"
HASH_FILE=".deploy/php-image.context-sha256"

need_build=0
if [ ! -f "$HASH_FILE" ] || [ "$(cat "$HASH_FILE")" != "$PHP_BUILD_HASH" ]; then
	need_build=1
elif ! docker image inspect efemer:latest >/dev/null 2>&1; then
	need_build=1
fi

if [ "$need_build" -eq 1 ]; then
	echo "PHP image build context changed (or image missing) — rebuilding..."
	mkdir -p .deploy
	"${COMPOSE[@]}" build php
	echo "$PHP_BUILD_HASH" > "$HASH_FILE"
else
	echo "PHP image build context unchanged — skipping build."
fi

"${COMPOSE[@]}" up -d --no-build
echo "Installing Composer dependencies..."
"${COMPOSE[@]}" exec -T -u "$(id -u):$(id -g)" php composer install --no-dev --optimize-autoloader --no-interaction
"${COMPOSE[@]}" exec -T php php bin/migrate.php

echo "Efemer containers are up."
