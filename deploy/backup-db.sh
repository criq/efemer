#!/usr/bin/env bash
# Dump MySQL and upload to gs://efemer/db/{DEV|PROD}/YYYY/MM/DD/efemer-HHMMSS.sql.gz
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
	echo "Missing .env" >&2
	exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

APP_ENV_UPPER="$(echo "${APP_ENV:-DEVELOPMENT}" | tr '[:lower:]' '[:upper:]')"
case "$APP_ENV_UPPER" in
	PRODUCTION|PROD) SHARD="PROD" ;;
	STAGING|STAGE) SHARD="STAGE" ;;
	*) SHARD="DEV" ;;
esac

if [ -f docker-compose.production.yml ] && { [ "${APP_ENV_UPPER}" = "PRODUCTION" ] || [ "${APP_ENV_UPPER}" = "PROD" ]; }; then
	COMPOSE=(docker compose -f docker-compose.production.yml)
else
	COMPOSE=(docker compose)
fi

STAMP="$(date -u +%Y%m%d-%H%M%S)"
DAY_PATH="$(date -u +%Y/%m/%d)"
LOCAL_DIR="${ROOT}/tmp/db-backup"
if ! mkdir -p "$LOCAL_DIR" 2>/dev/null; then
	LOCAL_DIR="${HOME}/efemer-db-backup"
	mkdir -p "$LOCAL_DIR"
fi
LOCAL_SQL="${LOCAL_DIR}/efemer-${STAMP}.sql"
LOCAL_GZ="${LOCAL_SQL}.gz"
REMOTE="gs://efemer/db/${SHARD}/${DAY_PATH}/efemer-${STAMP}.sql.gz"
KEY_FILE="${ROOT}/.secret/efemer-storage@efemer-456414.iam.gserviceaccount.com.json"

echo "Dumping MySQL (${SHARD})…"
"${COMPOSE[@]}" exec -T mysql mysqldump \
	-uefemer -p"${MYSQL_PASSWORD}" \
	--single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF \
	--routines --triggers \
	efemer > "$LOCAL_SQL" 2> >(grep -v 'Using a password' >&2 || true)

gzip -9 -f "$LOCAL_SQL"
SIZE="$(wc -c < "$LOCAL_GZ" | tr -d ' ')"
echo "Compressed dump: ${SIZE} bytes"

echo "Uploading ${REMOTE}…"
if [ -f "$KEY_FILE" ]; then
	CLOUDSDK_AUTH_CREDENTIAL_FILE_OVERRIDE="$KEY_FILE" \
		gcloud storage cp "$LOCAL_GZ" "$REMOTE" \
		--project=efemer-456414 \
		--quiet
else
	gcloud storage cp "$LOCAL_GZ" "$REMOTE" \
		--project=efemer-456414 \
		--account="${GCLOUD_ACCOUNT:-jablunka@gmail.com}" \
		--quiet
fi

# Keep a couple of local copies; GCS is the source of truth.
ls -1t "$LOCAL_DIR"/efemer-*.sql.gz 2>/dev/null | tail -n +4 | xargs -r rm -f

echo "Backup complete: ${REMOTE}"
