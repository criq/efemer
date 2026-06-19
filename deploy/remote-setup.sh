#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
	echo "Missing .env — copy from deploy/env.production.example and configure." >&2
	exit 1
fi

docker compose -f docker-compose.production.yml build php
docker compose -f docker-compose.production.yml up -d
docker compose -f docker-compose.production.yml exec -T php php bin/migrate.php

echo "Efemer containers are up."
