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
- [ ] Verify production
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
2. Runs `composer install --no-dev` locally
3. Rsyncs app to `jan@34.179.230.234:~/efemer` (excludes `.env`, `vendor` in main sync — then syncs `vendor/` and `.secret/` separately)
4. Runs `deploy/remote-setup.sh` on the server via `gcloud compute ssh`

`remote-setup.sh` builds PHP image, starts containers, runs **production migrations**:

```bash
docker compose -f docker-compose.production.yml exec -T php php bin/migrate.php
```

### Deploy failures

| Error | Fix |
|-------|-----|
| `composer: not found` in container | Should not happen — `deploy.sh` rsyncs `vendor/`. Re-run `./deploy.sh`. |
| rsync vendor permission denied | On server: `cd ~/efemer && docker compose -f docker-compose.production.yml down && sudo rm -rf vendor && mkdir vendor && chown jan:jan vendor`, then re-run `./deploy.sh`. |
| Missing `.env` on server | Create `~/efemer/.env` from `deploy/env.production.example` (see deployment rule). |
| SSH / gcloud auth | Use `~/.ssh/google_compute_engine`, project `aukce-496911`, zone `europe-west3-c`, VM `curios-vm`. |

## Step 5: Verify production

After `Deployment complete.`:

```bash
# Containers running
gcloud compute ssh curios-vm --zone=europe-west3-c --project=aukce-496911 \
  --command="docker ps --format 'table {{.Names}}\t{{.Status}}' | grep efemer"

# App responds (via Caddy on server)
gcloud compute ssh curios-vm --zone=europe-west3-c --project=aukce-496911 \
  --command="curl -s -o /dev/null -w '%{http_code}' -H 'Host: efemer.jabli.cz' http://127.0.0.1:52184/admin"
```

Expect HTTP `302` from `/admin` (redirect to login).

Optional: `curl -sI https://efemer.jabli.cz/admin` from local machine — requires DNS A record to `34.179.230.234`.

Report to the user:

- Commits pushed (SHAs or messages)
- Deploy script result
- Migration output (local + production if schema changed)
- Verification status
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
