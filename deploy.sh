#!/usr/bin/env bash
set -euo pipefail

GCP_PROJECT="aukce-496911"
GCP_ZONE="europe-west3-c"
VM_NAME="curios-vm"
VM_IP="34.179.230.234"
REMOTE_DIR="/home/jan/efemer"

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "Building frontend assets..."
npm install --no-fund --no-audit --ignore-scripts
npx webpack --config webpack.config.js

echo "Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

SSH_KEY_FILE="${HOME}/.ssh/google_compute_engine"

echo "Syncing to ${VM_NAME}..."
ssh -i "${SSH_KEY_FILE}" -o StrictHostKeyChecking=no "jan@${VM_IP}" "mkdir -p ${REMOTE_DIR}/.rsync-partial ${REMOTE_DIR}/data/mysql ${REMOTE_DIR}/logs ${REMOTE_DIR}/tmp ${REMOTE_DIR}/.secret"

rsync -avz \
	--delay-updates \
	--temp-dir=.rsync-partial \
	-e "ssh -i ${SSH_KEY_FILE} -o StrictHostKeyChecking=no" \
	--exclude="node_modules" \
	--exclude="vendor" \
	--exclude="data/mysql" \
	--exclude=".git" \
	--exclude="logs" \
	--exclude="tmp" \
	--exclude=".env" \
	--exclude=".DS_Store" \
	--exclude=".rsync-partial" \
	./ "jan@${VM_IP}:${REMOTE_DIR}/"

rsync -avz \
	-e "ssh -i ${SSH_KEY_FILE} -o StrictHostKeyChecking=no" \
	.secret/ "jan@${VM_IP}:${REMOTE_DIR}/.secret/"

rsync -avz \
	-e "ssh -i ${SSH_KEY_FILE} -o StrictHostKeyChecking=no" \
	vendor/ "jan@${VM_IP}:${REMOTE_DIR}/vendor/"

echo "Remote setup..."
gcloud compute ssh "${VM_NAME}" \
	--zone="${GCP_ZONE}" \
	--project="${GCP_PROJECT}" \
	--command="bash ${REMOTE_DIR}/deploy/remote-setup.sh"

echo "Deployment complete."
