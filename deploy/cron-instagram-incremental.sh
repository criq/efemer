#!/usr/bin/env bash
# Twice-daily Instagram ingest via Instaloader (sessionid cookie; no archive API).
# Dump → ImportInstagramDump → KeywordIngest → MaterializePosts.
# Keyword + Materialize always run so already-loaded items still go public
# even if Instaloader fails (expired session, rate limit, etc.).
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
PY="${VENV}/bin/python"
ACCOUNTS=(efemer_flowers himmlhaus)
COUNT="${INSTAGRAM_COUNT:-20}"
dump_failed=0

mkdir -p logs data/instagram

ensure_instaloader() {
	if [ -x "$PY" ] && "$PY" -c "import instaloader" 2>/dev/null; then
		return 0
	fi
	echo "Bootstrapping Instaloader venv at ${VENV}..."
	python3 -m venv "$VENV"
	"${VENV}/bin/pip" install -U pip instaloader
}

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) Instagram Instaloader incremental ==="

ensure_instaloader

if ! "$PY" "${ROOT}/deploy/instaloader-incremental.py" \
	--root "$ROOT" \
	--count "$COUNT" \
	"${ACCOUNTS[@]}"
then
	echo "WARN: Instaloader dump failed — continuing with import/keyword/materialize." >&2
	dump_failed=1
fi

# Import is safe when the dump dir is empty / unchanged.
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.ImportInstagramDump -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
"${COMPOSE[@]}" exec -T php php bin/cli.php jobs:run App.Classes.Jobs.Ingest.MaterializePosts -f

echo "=== $(date -u +%Y-%m-%dT%H:%M:%SZ) done ==="
exit "$dump_failed"
