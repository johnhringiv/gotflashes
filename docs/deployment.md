# Deployment & Operations

Operational guide for deploying and managing G.O.T. Flashes: the Docker container, staging
environments, and the Cloudflare edge in front of it.

## Configuration

Most environment variables are pre-configured in `docker/.env.docker`. You only need to set:

**Required:**
- `APP_KEY` - Generate with: `docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"`
- `TRUSTED_PROXY_IP` - Reverse-proxy (HAProxy) IP. Currently **inert defense-in-depth**: nginx resolves the real client IP from Cloudflare's `CF-Connecting-IP` header (`docker/nginx.conf`) and rewrites `REMOTE_ADDR` before PHP sees it, so `$request->ip()` does not depend on this and HTTPS is owned by `forceScheme`/`APP_URL`. Set it for config consistency; do not point IP logging or rate-limiting logic at it. See CLAUDE.md → "Proxy & Real Client IP".
- `RESEND_KEY` - API key for [Resend](https://resend.com) email service. Required for email verification and notifications. Get your key from the Resend dashboard.

**Optional:**
- `BASIC_AUTH_USERNAME` - HTTP basic auth for staging/preview environments
- `BASIC_AUTH_PASSWORD` - Leave both empty to disable
- `START_YEAR` - Controls grace period logic for logging previous year entries (default: `2026`). The January grace period (allowing previous year entries) only applies when current year > START_YEAR. Example: With `START_YEAR=2026`, January 2026 allows only 2026 entries, but January 2027+ allows December 2026 entries.

The container runs on port 8080 (HTTP only). Put a reverse proxy in front for SSL termination.

## Staging / dev environment (e.g. dev.gotflashes.com)

Run non-production clones as **`APP_ENV=staging`** (not `production`). That single value engages the
privacy protections and the non-production mail allowlist, and keeps `APP_DEBUG` distinct from prod.
Same `Cloudflare → HAProxy → container` request path as production.

**Privacy — two independent layers, both keyed off `APP_ENV != production`:**
- `PreventIndexingNonProduction` adds `X-Robots-Tag: noindex, nofollow, noarchive` to every response —
  the actual no-index *guarantee* (prevents indexing, not just crawling; works for non-HTML responses;
  holds even if basic-auth credentials are blank).
- `BasicAuthMiddleware` gates all envs except `local`/`testing` when `BASIC_AUTH_*` are set (401 to
  crawlers and casual visitors). `/up` stays exempt for HAProxy health checks.

**Environment variable deltas from prod:**

| Var | Dev value | Why |
| --- | --- | --- |
| `APP_ENV` | `staging` | Engages noindex + basic-auth gate + mail allowlist; distinct from prod. |
| `APP_KEY` | new (`php artisan key:generate`) | Separate session/encryption integrity — don't reuse prod's. |
| `APP_URL` | `https://dev.gotflashes.com` | Drives forceScheme, generated URLs, reset/verify links, sitemap base. |
| `APP_DEBUG` | `false` | Public/CF-reachable; `true` leaks stack traces even behind basic auth. |
| `DB_DATABASE` | separate file/volume | **Never point dev at prod's DB.** |
| `MAIL_MAILER` | `log` (safest) or `resend` with a **separate** `RESEND_KEY` | Don't burn prod's quota or send real mail from dev. |
| `BASIC_AUTH_USERNAME` / `BASIC_AUTH_PASSWORD` | non-empty | The access gate. Blank = no gate (noindex still applies). |
| `APP_NAME` | `"G.O.T. Flashes (Staging)"` (optional) | Distinguishes UI/email subjects from prod. |

**Keep the same as prod:** `SESSION_SECURE_COOKIE=true` (dev is HTTPS via HAProxy),
`CACHE_STORE=database`, `TRUSTED_PROXY_IP` (inert but consistent), locale, `START_YEAR`, `BCRYPT_ROUNDS`.

The two critical isolation points are a **separate `DB_DATABASE`** and **separate mail** (`log` or a
distinct Resend key) — everything else is keyed off `APP_ENV`.

## Container Startup

The entrypoint automatically:
1. Creates storage directories
2. Runs database migrations
3. Caches routes/config/views
4. Starts nginx, PHP-FPM, queue worker, and scheduler via supervisor

## Management Commands

To Clear rebuild and run
```bash
docker stop gotflashes && docker rm gotflashes
docker build -t gotflashes:latest .
docker run -d --name gotflashes -p 8081:8080 \
  -e DB_DATABASE=/var/www/html/database/data/database.sqlite \
  -v $(pwd)/database/data:/var/www/html/database/data \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  -v $(pwd)/backups:/var/www/html/storage/app/backups \
  --env-file .env gotflashes:latest
```

To Save
```bash
docker save -o gotflashes.img gotflashes:latest
```

Misc

```bash
# View logs
docker logs -f gotflashes

# Restart container
docker restart gotflashes

# Run artisan commands
docker exec gotflashes php artisan tinker
docker exec gotflashes php artisan migrate:status
docker exec gotflashes php artisan queue:work

# Check container health
docker ps -f name=gotflashes
docker stats gotflashes

# Access shell
docker exec -it gotflashes sh
```

## Architecture

- **Alpine Linux + PHP 8.4**: ~175MB image
- **Multi-stage build**: Node build → production image
- **Supervisor**: Manages nginx, PHP-FPM, queue worker, scheduler
- **SQLite**: Persisted via volume mounts

**Volume mounts (persistent data):**
- `./database/data` → `/var/www/html/database/data` (SQLite database + WAL files)
- `./storage/logs` → `/var/www/html/storage/logs` (application logs)
- `./backups` → `/var/www/html/storage/app/backups` (daily database backups, 90-day retention)

**Why mount `database/data` instead of `database`?**
- Mounting `./database` would overwrite the container's migrations folder
- Using a `data` subdirectory keeps database files persistent while preserving migrations in the container
- SQLite WAL files (`.sqlite-wal`, `.sqlite-shm`) automatically persist alongside the main database file

## Cloudflare Maintenance Worker

A Cloudflare Worker (`workers/worker.js`, deployed via `workers/wrangler.toml`) serves a branded
"Careening the Hull" maintenance page from Cloudflare's edge when the origin is unreachable, so
users see an on-brand `503` instead of Cloudflare's raw error screen. We're on Cloudflare's
**Free plan**, where the paid Error Pages / Custom Errors features aren't available, so a Worker
is the only option; at ~1% of the free Workers request limit it costs nothing. The full design
rationale lives in the `workers/worker.js` comments — the essentials below are the parts that
live *outside* the repo (dashboard config and homelab-topology facts).

**What triggers the page** (everything else passes through untouched):
- **Origin fully unreachable** (pfSense/host reboot) — Cloudflare resolves the Worker's `fetch()`
  with a `520`–`526`/`530` status (it does *not* throw), which the Worker matches.
- **Gateway error, no live backend** (docker reset, or PHP-FPM dead while nginx is up) —
  HAProxy/nginx return `502`/`503`/`504`.
- **Application errors are never masked:** `500`/`501` (real Laravel bugs) and all `2xx`/`3xx`/`4xx`
  pass straight through. Laravel's own `artisan down` returns `503`, which correctly shows the page.

**Pre-ship check — do this *before* the Worker is live.** The `{502, 503, 504}` gateway subset assumes
HAProxy returns `503` when its backend is gone. Confirm by taking the **app container/pod** down and
leaving **HAProxy up** (killing HAProxy instead would exercise the deterministic `52x` origin-down path,
*not* the uncertain gateway code you're checking), then `curl -I https://gotflashes.com`. Timing matters:
run it *before* deploying the Worker — once live, the Worker returns its own `503` and masks HAProxy's
real status (post-deploy you'd have to curl the origin directly, bypassing Cloudflare). The `52x`/`530`
codes need no check; Cloudflare generates them itself. **Confirmed `503` on 2026-07-17** — revisit only
if HAProxy's error handling changes.

**Routes (`wrangler.toml`) — both hostnames:**
```toml
routes = [
  { pattern = "gotflashes.com/*", zone_name = "gotflashes.com" },
  { pattern = "www.gotflashes.com/*", zone_name = "gotflashes.com" }
]
```
The `www` route is **load-bearing**: the `www → apex` redirect is done by **HAProxy on pfSense**
(`redirect prefix … regsub(^www\.,,i) code 301`), *not* at the Cloudflare edge. When the origin is
down that redirect can't fire, so without its own route a `www.` visitor would get Cloudflare's raw
error page. (Aside: that HAProxy rule redirects to `http://`, so a healthy `www.` load takes an
extra hop through Cloudflare's Always-Use-HTTPS; switching the rule to `https://` would save a
round-trip — pfSense config, not this repo.)

**Fail Open — manual dashboard step, NOT in `wrangler.toml`:** after deploying, set **both** routes'
failure mode to **Fail open** (Workers & Pages → Settings → Domains & Routes → each route → Failure
mode). Then if the Worker ever can't run (e.g. request limit hit), requests bypass it to origin —
users see the normal site rather than Cloudflare's 1027 error. This Worker is a convenience layer,
so a Worker outage should degrade to "normal site," never "site broken."

**Deploying (first-time sequence):**
1. **Pre-ship check** (above) — confirm HAProxy's no-backend status *before* anything goes live.
2. `npx wrangler login` — one-time, interactive; opens a browser to authorize against the Cloudflare
   account holding the `gotflashes.com` zone. (In a Claude Code session, run it as `! npx wrangler login`.)
3. `cd workers && npx wrangler deploy` — uploads the Worker and binds both routes; live at the edge
   within seconds, in front of all production traffic.
4. **Set Fail open on both routes** (above) — `wrangler` can't do this; it's the one manual dashboard step.
5. **Verify** (below).

Redeploys are just step 3 (the routes and Fail-open setting persist). Confirm the account first with
`npx wrangler whoami`.

**Rollback:** remove the Worker route(s) (`wrangler delete` or the dashboard) → traffic goes straight
to origin, exactly as before. The Worker holds no persistent state, so removal is instant.

**Verify after deploy:**
1. Normal site loads unchanged with the Worker active — homepage `200`, `/up` `200`, and `www.` still
   `301`s to the apex (pass-through).
2. A forced Laravel `500` (temporary throwing route) comes through — **not** the maintenance page.
3. `docker stop` the app container (HAProxy up) → Careening page at `503`; `docker start` → site returns.
4. During a full origin outage, `www.gotflashes.com` shows the page (proves the `www` route answers
   directly, since the HAProxy redirect can't fire).
5. During an outage, a `.js`/XHR request (`Sec-Fetch-Mode: cors`) gets a bare `503`, while a browser
   navigation gets the HTML page. Confirm headers: `503`, `Retry-After: 120`, `Cache-Control: no-store`.

> **Outage alerting** is intentionally *not* built in — the page tells visitors to email
> `admin@gotflashes.com` rather than claiming automated notification. Adding an uptime monitor on
> `/up` is tracked in [issue #47](https://github.com/johnhringiv/gotflashes/issues/47); the honest
> `503` status makes the site outage-detectable by such a monitor.
