---
name: deploy
description: Commit changes, deploy efemer to production on curios-vm, and run local and production migrations. Use when the user asks to deploy, ship to production, release, push live, or commit and deploy.
---

# Deploy

End-to-end release workflow for **efemer.jabli.cz** on curios-vm. Read `.cursor/rules/deployment.mdc` for server details.

Also read `.cursor/skills/commit/SKILL.md` when the commit step runs.

## When to use

User says: deploy, ship, release, push live, commit and deploy, update production.

## Checklist

Copy and track progress:

```
Deploy progress:
- [ ] Pre-flight
- [ ] Local migrations (if schema changed)
- [ ] Commit + push (logical chunks)
- [ ] Run ./deploy.sh
- [ ] Verify production (public URL + loopback)
- [ ] If down: investigate and fix until up again
```

## Step 1: Pre-flight

1. `git status` — know what will be committed and deployed.
2. Confirm no secrets in the diff (`.env`, `.secret/`, credentials).
3. If migration files changed (`app/Classes/Database/Migrations/`, `Migrator.php`), plan to run local migrations before commit.

**Do not deploy** uncommitted work the user did not ask to commit — ask first if unclear.

## Step 2: Local migrations

Required when schema changed. Skip if no migration files in the change set.

```bash
docker compose exec -T php php bin/migrate.php
```

Success: output ends with `Migrations complete.`

If the PHP container is missing or `bin/` is not mounted:

```bash
docker compose up -d php
```

## Step 3: Commit and push

Follow the **commit** skill:

1. Split into logical chunks (migrations before code that depends on them).
2. Stage, commit with HEREDOC message, push after **each** chunk.
3. Working tree should be clean (except intentionally ignored files) before deploy.

If the user asked only to deploy and everything is already committed and pushed, skip this step.

## Step 4: Deploy

From repo root:

```bash
./deploy.sh
```

This script:

1. Builds webpack assets (`npm` + `webpack`)
2. Rsyncs app to `jan@34.179.230.234:~/efemer` (excludes `.env`, `vendor`, `data/mysql` — then syncs `.secret/`)
3. Runs `deploy/remote-setup.sh` on the server via `gcloud compute ssh`

`remote-setup.sh` rebuilds the PHP image **only when `docker/php/` changed** (hash in `.deploy/php-image.context-sha256`), runs `up -d --no-build`, **`composer install --no-dev` in the PHP container**, then migrations.

```bash
docker compose -f docker-compose.production.yml build php   # skipped when context unchanged
docker compose -f docker-compose.production.yml up -d --no-build
docker compose -f docker-compose.production.yml exec -T php composer install --no-dev --optimize-autoloader --no-interaction
docker compose -f docker-compose.production.yml exec -T php php bin/migrate.php
```

### Deploy failures

| Error | Fix |
|-------|-----|
| `composer: not found` in container | Rebuild PHP image (`docker/php/` changed) — Dockerfile includes Composer. Delete `.deploy/php-image.context-sha256` on server to force rebuild. |
| rsync vendor permission denied | On server: `cd ~/efemer && docker compose -f docker-compose.production.yml down && sudo rm -rf vendor && mkdir vendor && chown jan:jan vendor`, then re-run `./deploy.sh`. |
| Missing `.env` on server | Create `~/efemer/.env` from `deploy/env.production.example` (see deployment rule). |
| SSH / gcloud auth | Use `~/.ssh/google_compute_engine`, project `aukce-496911`, zone `europe-west3-c`, VM `curios-vm`. |

## Step 5: Verify production (required — do not stop here)

After `Deployment complete.`, confirm the site is **actually up**. This step is not optional.

```bash
# Public HTTPS (primary check)
curl -sI -o /dev/null -w '%{http_code}' https://efemer.jabli.cz/admin

# Containers running
gcloud compute ssh curios-vm --zone=europe-west3-c --project=aukce-496911 \
  --command="docker ps --format 'table {{.Names}}\t{{.Status}}' | grep efemer"

# App responds on loopback (via Caddy upstream)
gcloud compute ssh curios-vm --zone=europe-west3-c --project=aukce-496911 \
  --command="curl -s -o /dev/null -w '%{http_code}' -H 'Host: efemer.jabli.cz' http://127.0.0.1:52184/admin"
```

**Success:** HTTP `302` from `/admin` (redirect to login). Homepage may be `404` if no public page exists — that is OK.

**If the site is down or unhealthy:** investigate and fix until it responds again. Do not report deploy as done while production is broken. Common fixes:

1. Re-run `./deploy.sh` if rsync or remote-setup failed partway
2. Check container logs: `docker compose -f docker-compose.production.yml logs --tail=50 php nginx`
3. Fix vendor permissions (see deploy failures table)
4. Ensure `.env` and `.secret/` exist on the server
5. Restart stack: `docker compose -f docker-compose.production.yml up -d`

Report to the user:

- Commits pushed (SHAs or messages)
- Deploy script result
- Migration output (local + production if schema changed)
- Verification status (public URL + loopback)
- What was broken and how it was fixed (if anything)
- Any DNS/TLS issues if the public URL does not work

## First-time / infra changes

Not part of routine deploy — only when setting up or changing hosting:

- Add Caddy block for `efemer.jabli.cz` → `127.0.0.1:52184` in `/etc/caddy/Caddyfile`, reload Caddy
- DNS A record `efemer.jabli.cz` → `34.179.230.234`
- Initial `~/efemer/.env` and `.secret/` on server

See `.cursor/rules/deployment.mdc`.

## Do not

- Force push to main
- Commit `.env` or `.secret/` contents
- Skip local migrations when schema changed
- Skip push before deploy (production should match `origin/main`)
- Amend commits that were already pushed
