#!/usr/bin/env bash
# Twice-daily Instagram ingest via Instaloader (no archive API).
# Dump → ImportInstagramDump → KeywordIngest → MaterializePosts.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
	echo "Missing .env" >&2
	exit 1
fi

COMPOSE=(docker compose -f docker-compose.production.yml)
# Keep venv outside tmp/ (PHP/www-data owns that tree on production).
VENV="${ROOT}/.venv-instagram"
INSTALOADER="${VENV}/bin/instaloader"
ACCOUNTS=(efemer_flowers himmlhaus)
# Cap per account so an empty dump dir never tries a full archive.
COUNT="${INSTAGRAM_COUNT:-20}"

mkdir -p logs data/instagram

ensure_instaloader() {
	if [ -x "$INSTALOADER" ]; then
		return 0
	fi
	echo "Bootstrapping Instaloader venv at ${VENV}..."
	python3 -m venv "$VENV"
	"${VENV}/bin/pip" install -U pip instaloader
}

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) Instagram Instaloader incremental ==="

ensure_instaloader

# Anonymous scrape; --fast-update stops at the first already-downloaded post.
# --count caps how many newest posts to consider (safe on empty dump dirs).
"$INSTALOADER" \
	--no-videos \
	--fast-update \
	--count "$COUNT" \
	--dirname-pattern "${ROOT}/data/instagram/{profile}" \
	--filename-pattern "{date_utc}_UTC" \
	"${ACCOUNTS[@]}"

"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ImportInstagramDump -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.MaterializePosts -f

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) done ==="
