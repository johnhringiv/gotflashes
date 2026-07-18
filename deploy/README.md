# Deploy runbook: CI → GHCR → poll (continuous deploy from main)

gotflashes ships as the self-contained Docker image built by
[`Dockerfile`](../Dockerfile). Deploys are **pull-based**: the production host
sits behind pfSense/Cloudflare with no inbound access, so CI publishes an
image and the host polls for it. There is **no manual promote step** — every
push to `main` that passes CI is live within one poll cycle. Since releases
reach `main` only via deliberate `dev → main` merges (see
[CONTRIBUTING](../docs/CONTRIBUTING.md)), merging to `main` *is* the release
decision.

```
push to main ──► CI (quality checks + docker smoke) ──► GHCR :latest  (+ :sha-<commit>)
                                                             │
                            host cron: poll-deploy.sh ───────┘ pull :latest when digest changes
                                             │
                                             └─► deploy.sh: replace container, health-gate on /up
```

## Pipeline

**CI** (`.github/workflows/check.yml`) runs on every push and PR: the full
quality suite (`composer check`) plus a Docker job that builds the image and
smoke-tests it (boot, migrate, `/up` 200, homepage 200). On push to `main`
(or a `v*` tag) the publish job pushes to GHCR:

- `ghcr.io/johnhringiv/gotflashes:latest` — newest main (**what the host deploys**)
- `…:sha-<commit>` — every commit (pin one to roll back)
- `…:v1` — a `v*` git tag, verbatim

A monthly scheduled run rebuilds so the base image picks up CVE fixes. The
checkouts that feed a Docker build use `lfs: true` — the award badges,
favicon, and logo in `public/` are Git LFS, and without it the image would
ship pointer files instead of images.

Public repo → public image, so no registry auth is needed on the host.

## What a deploy does (and the brief outage)

The app is **one container**: its SQLite database can't be served by two app
instances mid-migration, so there is no rolling pair here (unlike
johnhringiv.com). `deploy.sh` stops the old container, starts the new one,
and health-gates on `/up` — the entrypoint runs `php artisan migrate --force`
before nginx serves, so the gate also proves migrations applied. The gap is
the seconds-to-a-minute the new container needs to boot; during it the
Cloudflare maintenance Worker serves the branded "Careening the Hull" 503
(see `docs/deployment.md`), and HAProxy/Cloudflare pass traffic again the
moment `/up` answers.

Data persists across deploys via bind mounts under `DATA_DIR` (the existing
prod layout — on the Synology, `/volume3/docker_ssd/gotflashes`):

| Host path          | Container path                      | Holds                               |
| ------------------ | ----------------------------------- | ----------------------------------- |
| `$DATA_DIR/data`   | `/var/www/html/database/data`       | SQLite DB + WAL files               |
| `$DATA_DIR/logs`   | `/var/www/html/storage/logs`        | app + nginx logs                    |
| `$DATA_DIR/backup` | `/var/www/html/storage/app/backups` | daily DB backups (90-day retention) |

## Host setup (one time)

1. Copy `deploy.sh`, `poll-deploy.sh`, and `gotflashes.env.example` to a
   directory on the host; `cp gotflashes.env.example gotflashes.env`, fill it
   in (`chmod 600 gotflashes.env`). `DATA_DIR` must point at the existing
   data directory — the one already holding `data/`, `logs/`, and `backup/`.
2. First deploy by hand. On Synology/DSM the Docker socket is root-only, so
   the scripts need `sudo`:
   ```sh
   sudo sh deploy.sh gotflashes.env
   ```
   Migrating from the old hand-run container? See
   [Cutover from the hand-run container](#cutover-from-the-hand-run-container)
   first.
3. Schedule the poller every 5 minutes **as root** — on Synology via Task
   Scheduler (below); on a generic host via cron:
   ```
   */5 * * * * cd /path/to/deploy && sh poll-deploy.sh gotflashes.env >> poll-deploy.log 2>&1
   ```
   Have cron/DSM notify you on a non-zero exit — the only time the poller
   signals a problem.

### Synology (DSM) Task Scheduler

DSM overwrites the system crontab, so schedule the poller through the UI:

1. **Control Panel → Task Scheduler → Create → Scheduled Task → User-defined
   script**.
2. **General**: name it (e.g. `gotflashes poll-deploy`); **User: `root`**
   (Docker is root-only on DSM); Enabled.
3. **Schedule**: Daily, First run `00:00`, **Frequency: Every 5 minutes**,
   Last run `23:55`.
4. **Task Settings → Run command → User-defined script**:
   ```sh
   cd /path/to/deploy_scripts/gotflashes && sh poll-deploy.sh gotflashes.env >> poll-deploy.log 2>&1
   ```
   Also tick **"Send run details by email … only when the script terminates
   abnormally"** — `poll-deploy.sh` exits non-zero only on a real problem
   (unhealthy deploy, or the edge unreachable), so this pages you exactly
   then and stays quiet on the routine no-op cycles.
5. Select the task → **Run** to fire it once; check `poll-deploy.log`.

DSM gotchas: don't use `--cpus` (DSM kernels lack the CFS scheduler — the
scripts don't); if you replace the container by hand, remove the old one
first so the HAProxy backend port is free (`deploy.sh` only removes the
container named in `CONTAINER`).

## Cutover from the hand-run container

One-time migration from the old dated container (`gotflashes-2026-1-1`,
HAProxy backend `:8085`). The new container comes up on `:8087`, so both run
side by side until HAProxy is switched — but they share `DATA_DIR`, so during
the window:

- the new container applies any **pending migrations to the live database**
  the old container is still serving (fine for additive migrations, which is
  the norm here — but it is one-way);
- **both** queue workers poll the same `jobs` table and both schedulers run;
- both append to the same log files.

Keep the window short: verify, cut over, retire.

1. `sudo sh deploy.sh gotflashes.env` — starts `gotflashes` on `:8087`
   against the live data; the old container keeps serving `:8085`.
2. Verify the new container directly: `curl http://localhost:8087/up`, click
   around via the HAProxy stats page or an SSH tunnel if you want more than
   the health check.
3. Switch the HAProxy backend on pfSense from `:8085` to `:8087`; confirm the
   edge (`curl https://gotflashes.com/up`).
4. Retire the old container:
   ```sh
   sudo docker stop gotflashes-2026-1-1 && sudo docker rm gotflashes-2026-1-1
   ```
5. Now schedule the poller (Host setup step 3). Don't schedule it before the
   cutover is done — its self-heal/redeploy logic manages only the new
   container.

## Continuous deploy loop

`poll-deploy.sh`, run from the scheduler:

- **Unchanged `:latest`, container running, edge healthy** → cheap no-op
  (one manifest check + one `/up` probe through Cloudflare).
- **Changed `:latest` digest** (a new push to `main` landed) → runs
  `deploy.sh`, then verifies the edge; exits non-zero if it never comes up.
- **Container not running on an unchanged tag** (e.g. after a host reboot,
  before Docker's `restart unless-stopped` has settled) → self-heals by
  redeploying.
- **Container running but the edge unreachable** → exits non-zero *without*
  redeploying (Cloudflare/HAProxy/DNS trouble a redeploy won't fix).

## Rollback

Immediate stopgap — on the host, pin the last-good build and redeploy:

```sh
sudo IMAGE=ghcr.io/johnhringiv/gotflashes:sha-<commit> sh deploy.sh gotflashes.env
```

Get the `sha-<commit>` from the CI run (or `git log`) of the good commit.

**The pin is only a stopgap:** the poller watches `:latest`, so the next
cycle re-pulls the bad build. For a durable rollback, also **`git revert` the
bad commit on `main` and push** — CI republishes `:latest` as the fixed build
and the poller converges on it. Pin first to stop the bleeding, then revert
to make it stick.

**Migrations caveat:** rolling back the image does not roll back the
database. Migrations here are additive as a rule, so an old image on a newer
schema is normally fine; if a bad release's migration was itself the problem,
restore the pre-deploy backup from `$DATA_DIR/backups` (daily, 02:00) before
pinning.

## Offline / manual image (fallback)

Build and `docker save` anywhere, copy the tarball to the host, and pass it
as the second argument — the pull is skipped and the loaded image is used:

```sh
docker build -t gotflashes:local .
docker save gotflashes:local | gzip > gotflashes.tar.gz
# on the host:
sudo IMAGE=gotflashes:local sh deploy.sh gotflashes.env gotflashes.tar.gz
```

This replaces the old `docker save` → copy `.img` → `docker load` →
recreate-by-hand ritual for the cases where you still need it.

## Staging instance (dev.gotflashes.com)

Same scripts, second env file: `cp gotflashes.env.example gotflashes-dev.env`
with a **different** `CONTAINER`, `PORT`, `DATA_DIR`, and `APP_KEY`, plus the
staging deltas (`APP_ENV=staging`, `APP_URL`, basic auth, `MAIL_MAILER=log`)
— rationale in `docs/deployment.md`. Run/schedule the poller with that env
file as the argument. Staging normally tracks the same `:latest`; point its
`IMAGE` at a `sha-…` tag to hold it on a specific build.

## Verify

```sh
curl -s -o /dev/null -w '%{http_code}\n' https://gotflashes.com/up   # 200
curl -s https://gotflashes.com/ | grep -i 'G.O.T. Flashes'           # sanity-check a page
sudo docker ps --filter name=gotflashes                              # container Up
sudo docker exec gotflashes php artisan migrate:status | tail -3
```
