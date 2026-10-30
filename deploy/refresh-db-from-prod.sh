#!/usr/bin/env bash
# Refresh local DEV MySQL from production (curios-vm).
# Rewrites gs://efemer/files/PROD/ → gs://efemer/files/DEV/ in the dump.
#
# Usage (from repo root on your laptop):
#   ./deploy/refresh-db-from-prod.sh
#   ./deploy/refresh-db-from-prod.sh --yes
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

YES=0
for arg in "$@"; do
	case "$arg" in
		-y|--yes) YES=1 ;;
		-h|--help)
			sed -n "2,8p" "$0"
			exit 0
			;;
	esac
done

if [ ! -f .env ]; then
	echo "Missing local .env" >&2
	exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

APP_ENV_UPPER="$(echo "${APP_ENV:-DEVELOPMENT}" | tr '[:lower:]' '[:upper:]')"
if [ "$APP_ENV_UPPER" = "PRODUCTION" ] || [ "$APP_ENV_UPPER" = "PROD" ]; then
	echo "Refusing to run: local APP_ENV is PRODUCTION. This script targets DEV only." >&2
	exit 1
fi

if [ -z "${MYSQL_PASSWORD:-}" ]; then
	echo "MYSQL_PASSWORD missing in local .env" >&2
	exit 1
fi

GCLOUD_ACCOUNT="${GCLOUD_ACCOUNT:-jablunka@gmail.com}"
GCLOUD_PROJECT_VM="${GCLOUD_PROJECT_VM:-aukce-496911}"
GCLOUD_ZONE="${GCLOUD_ZONE:-europe-west3-c}"
GCLOUD_VM="${GCLOUD_VM:-curios-vm}"
REMOTE_APP="${REMOTE_APP:-/home/jan/efemer2}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/google_compute_engine}"

STAMP="$(date -u +%Y%m%d-%H%M%S)"
WORK="${ROOT}/tmp/db-refresh"
mkdir -p "$WORK"
REMOTE_SQL_HOST="${HOME}/prod-sync-efemer/efemer-from-prod-${STAMP}.sql"
LOCAL_SQL="${WORK}/efemer-from-prod-${STAMP}.sql"
LOCAL_SQL_DEV="${WORK}/efemer-from-prod-${STAMP}-dev-uris.sql"

echo "This will REPLACE the local DEV database with a dump from ${GCLOUD_VM}."
if [ "$YES" -ne 1 ]; then
	read -r -p "Continue? [y/N] " ans
	case "$ans" in
		y|Y|yes|YES) ;;
		*) echo "Aborted."; exit 1 ;;
	esac
fi

echo "Dumping production MySQL on ${GCLOUD_VM}…"
gcloud compute ssh "$GCLOUD_VM" \
	--zone="$GCLOUD_ZONE" \
	--project="$GCLOUD_PROJECT_VM" \
	--account="$GCLOUD_ACCOUNT" \
	--command="
set -euo pipefail
cd '${REMOTE_APP}'
mkdir -p ~/prod-sync-efemer
docker compose -f docker-compose.production.yml exec -T mysql sh -c '
  mysqldump -uefemer -p\"\$MYSQL_PASSWORD\" efemer \
    --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF \
    --routines --triggers --complete-insert
' > '${REMOTE_SQL_HOST}' 2>/dev/null
wc -c '${REMOTE_SQL_HOST}'
"

echo "Downloading dump…"
rsync -az -e "ssh -i ${SSH_KEY} -o StrictHostKeyChecking=yes" \
	"jan@34.179.230.234:${REMOTE_SQL_HOST}" \
	"$LOCAL_SQL"

SIZE="$(wc -c < "$LOCAL_SQL" | tr -d ' ')"
if [ "$SIZE" -lt 1000 ]; then
	echo "Dump looks empty (${SIZE} bytes) — aborting." >&2
	exit 1
fi
echo "Dump size: ${SIZE} bytes"

echo "Rewriting storage URIs PROD → DEV…"
REFRESH_SQL_SRC="$LOCAL_SQL" REFRESH_SQL_DST="$LOCAL_SQL_DEV" python3 - <<'PY'
import os
from pathlib import Path
src = Path(os.environ["REFRESH_SQL_SRC"])
dst = Path(os.environ["REFRESH_SQL_DST"])
text = src.read_text()
n = text.count("gs://efemer/files/PROD/")
text = text.replace("gs://efemer/files/PROD/", "gs://efemer/files/DEV/")
# legacy top-level shards if any
text = text.replace("gs://efemer/PROD/", "gs://efemer/files/DEV/")
dst.write_text(text)
print(f"rewrote {n} files/PROD URIs → files/DEV")
print(f"remaining files/PROD: {dst.read_text().count('gs://efemer/files/PROD/')}")
PY

echo "Importing into local Docker MySQL…"
docker compose up -d mysql >/dev/null
# wait until accepting connections
for _ in $(seq 1 30); do
	if docker compose exec -T mysql mysqladmin ping -uefemer -p"${MYSQL_PASSWORD}" --silent 2>/dev/null; then
		break
	fi
	sleep 1
done

docker compose exec -T mysql mysql -uefemer -p"${MYSQL_PASSWORD}" efemer \
	--init-command="SET FOREIGN_KEY_CHECKS=0; SET UNIQUE_CHECKS=0; SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';" \
	< "$LOCAL_SQL_DEV" 2> >(grep -v 'Using a password' >&2 || true)

echo "Local counts:"
docker compose exec -T mysql mysql -N -uefemer -p"${MYSQL_PASSWORD}" efemer -e "
SELECT COUNT(*) AS posts FROM posts;
SELECT COUNT(*) AS invitations FROM posts WHERE isInvitation=1;
SELECT COUNT(*) AS ingest FROM ingest_items;
SELECT COUNT(*) AS storage_dev FROM storage_files WHERE uri LIKE 'gs://efemer/files/DEV/%';
SELECT COUNT(*) AS storage_prod FROM storage_files WHERE uri LIKE 'gs://efemer/files/PROD/%';
" 2>/dev/null

# Remote cleanup of large dump (keep local copy in tmp/)
gcloud compute ssh "$GCLOUD_VM" \
	--zone="$GCLOUD_ZONE" \
	--project="$GCLOUD_PROJECT_VM" \
	--account="$GCLOUD_ACCOUNT" \
	--command="rm -f '${REMOTE_SQL_HOST}'" >/dev/null

echo "Refresh complete. Local DEV DB now mirrors production (URIs → files/DEV/)."
echo "Dump kept at: ${LOCAL_SQL_DEV}"
