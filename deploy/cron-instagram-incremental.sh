#!/usr/bin/env bash
# Incremental Instagram ingest twice daily (no full backfill).
# Refresh → KeywordIngest → MaterializePosts so new posts can go public.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
	echo "Missing .env" >&2
	exit 1
fi

COMPOSE=(docker compose -f docker-compose.production.yml)
LOG_DIR="${ROOT}/logs"
mkdir -p "$LOG_DIR"

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) Instagram incremental ==="

"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.RefreshInstagramAccounts -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.MaterializePosts -f

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) done ==="
