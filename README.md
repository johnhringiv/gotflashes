# G.O.T. Flashes Challenge Tracker

[![CI](https://github.com/johnhringiv/gotflashes/actions/workflows/check.yml/badge.svg?branch=main)](https://github.com/johnhringiv/gotflashes/actions/workflows/check.yml)
[![codecov](https://codecov.io/gh/johnhringiv/gotflashes/graph/badge.svg)](https://codecov.io/gh/johnhringiv/gotflashes)
[![site](https://img.shields.io/website?url=https%3A%2F%2Fgotflashes.com%2Fup&label=site)](https://gotflashes.com)
[![deployed](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fgotflashes.com%2Fversion.json&query=%24.short&label=deployed)](https://gotflashes.com)

A web application for tracking Lightning Class sailing activity and managing the G.O.T. Flashes Challenge awards program. This system helps Lightning sailors log their days on the water, track progress toward annual awards, and foster friendly competition within the sailing community.

**G.O.T. Flashes** stands for "**Get Out There** - FLASHES" - encouraging Lightning sailors to get their boats off the dock and onto the water!

**🌐 Live Application**: [https://gotflashes.com](https://gotflashes.com)

## About the G.O.T. Flashes Challenge

The G.O.T. Flashes Challenge encourages Lightning Class sailors to get on the water more frequently by recognizing their annual sailing activity. Participants earn awards at 10, 25, and 50+ days, with the simple goal: **Get the boat off the dock!**

### What Counts
- **Sailing Days**: Any time spent sailing on a Lightning (as skipper or crew) - unlimited
- **Non-Sailing Days**: Up to 5 days per year for boat/trailer maintenance or race committee work

### Award Tiers
- **10 Days**: First tier recognition
- **25 Days**: Second tier recognition
- **50+ Days**: Third tier recognition (including Burgee eligibility)

## Screenshots

### Lightning Log
![Lightning Log - Track your sailing activities](docs/screenshots/logbook.png)
*Activity logging with progress tracking, award badges, and activity history*

### Multi-Date Calendar Picker
![Multi-Date Calendar Picker](docs/screenshots/datepicker.png)
*Select multiple dates at once with existing entries marked*

### Community Statistics
![Community Statistics](docs/screenshots/stats.jpg)
*Public dashboard: community-goal progress, activity heatmap, growth and season charts, and fun facts*

### Award Fulfillment Dashboard (Admin)
![Award Fulfillment Dashboard](docs/screenshots/fulfillment.png)
*Admin interface for managing physical award mailings with batch operations and CSV export*

## Key Features

- **Activity logbook**: log sailing and non-sailing days (location, sail number, event type, notes) with a multi-date calendar picker; edit/delete within the current year plus a January grace period for the previous year; duplicate dates and future dates are blocked, and non-sailing days cap at 5 per year toward awards
- **Progress & awards**: dashboard progress bar with 10/25/50-day badges, plus an admin fulfillment dashboard (Earned → Processing → Sent, batch operations, CSV mailing-label export, discrepancy warnings)
- **Three leaderboards**: sailor, fleet, and district rankings with year-specific memberships, tie-breaking (total → sailing days → first entry → alphabetical), and your position highlighted
- **Community statistics** (public `/stats`): animated community-goal hero, headline counters, five self-hosted D3 charts, and fun facts — privacy-preserving aggregates only, every chart with an accessible data-table twin
- **Profiles & memberships**: personal details, mailing address, verified email changes (pending-email pattern), searchable district/fleet comboboxes with cross-filtering, per-year membership carry-forward ([details](docs/membership-year-end-logic.md)), and full profile+activity CSV export
- **Accounts**: session-based auth with registration, login, password reset, and soft email verification (use the app immediately; a banner nudges until verified); users can only see and modify their own entries
- **Admin tooling**: sailor activity logs with filters and audited CSV export; site settings (community goal) for the elevated super-admin tier
- **Self-hosted and production-ready**: no CDNs, all assets bundled locally; Docker containerization with continuous deploy

## Technology Stack

- **Backend**: Laravel 13 (PHP 8.5+)
- **Database**: SQLite with WAL mode
- **Email**: Resend (verification, password reset, award notifications)
- **Frontend**:
  - Hand-authored vanilla CSS (framework-free, OKLCH design tokens, ~9 kB gzipped)
  - Blade templates + Livewire v4 (reactive components)
  - Vanilla JavaScript with native `fetch()` API
  - Home-grown multi-date calendar picker and searchable comboboxes (no widget libraries)
- **Asset Bundling**: Vite
- **Deployment**: Docker (Alpine Linux + PHP-FPM + Nginx + Supervisor)

## Getting Started

### Quick Setup

```bash
git clone https://github.com/johnhringiv/gotflashes.git
cd gotflashes
git lfs install && git lfs pull
mkdir -p database/data && touch database/data/database.sqlite
composer setup
composer dev
```

Access at http://localhost:8000

See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for prerequisites and detailed environment setup (Ubuntu/WSL, nvm, etc.), the development workflow, code quality tooling, and testing.

### Creating Admin Users

After setup, create at least one admin user:

```bash
php artisan tinker
```

```php
$user = User::where('email', 'user@example.com')->first();
$user->is_admin = true;        // award administration (fulfillment, sailor logs)
$user->is_super_admin = true;  // elevated site-operator tier (site settings)
$user->save();
```

`is_admin` and `is_super_admin` are independent — grant `is_super_admin` to access `/admin/settings` (the community goal).

**Security Note:** Only grant admin access to trusted users — admins can view all user addresses and contact information and export user data via CSV.

## Deployment

Production runs the image CI publishes to GHCR on every push to `main`
(`ghcr.io/johnhringiv/gotflashes:latest`); the host polls and redeploys
automatically — **continuous deploy from `main`**.

- **[deploy/README.md](deploy/README.md)** — production host runbook: pipeline, host setup, rollback, staging instance
- **[docs/deployment.md](docs/deployment.md)** — operations guide: configuration, staging environments, management commands, the Cloudflare edge maintenance Worker

**Configuration:** only two secrets must be set — `APP_KEY` and `RESEND_KEY`. Everything else has sensible defaults (`.env.example` for local, `docker/.env.docker` baked into the image for production); the full variable reference lives in [docs/deployment.md](docs/deployment.md).

To build and run the image locally (no PHP/Node required on host):

```bash
# 1. Configure environment (secrets only; defaults are baked into the image)
cp deploy/gotflashes.env.example gotflashes.env
# Edit gotflashes.env: set APP_KEY and RESEND_KEY (this manual run mounts
# repo-relative paths directly, so DATA_DIR can stay empty)

# 2. Build and run
docker build -t gotflashes:local .
mkdir -p database/data storage/logs backups
docker run -d --name gotflashes --restart unless-stopped \
  -p 8080:8080 \
  -v $(pwd)/database/data:/var/www/html/database/data \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  -v $(pwd)/backups:/var/www/html/storage/app/backups \
  --env-file gotflashes.env \
  gotflashes:local
```

### Production Stack

- **Cloudflare**: DNS, CDN, and Email Routing (firewall restricts traffic to Cloudflare IPs only), plus an edge Worker serving a branded maintenance page during origin outages (`workers/`)
- **HAProxy**: SSL termination (ACME/Let's Encrypt) and reverse proxy
- **Docker container**: nginx + PHP-FPM + Supervisor

**Security Note:** Firewall-level restrictions ensure only Cloudflare IPs can reach the server, so nginx's realip module can safely resolve the real client IP from the `CF-Connecting-IP` header (set by Cloudflare, not client-spoofable) for logging and rate-limiting. Laravel's `trustProxies` (`TRUSTED_PROXY_IP`) is inert defense-in-depth — nginx already rewrites `REMOTE_ADDR`, so `$request->ip()` does not depend on it.

See this [guide](https://johnhringiv.com/secure-scalable-home-web-hosting) for the full deployment setup.

## Observability

Structured request logging (with automatic Livewire component context), slow query/request detection, security and admin audit channels, and error tracking with full context — written to `storage/logs/` (`php artisan pail` for live tailing). Thresholds and channels are documented in [docs/deployment.md](docs/deployment.md) and `.env.example`.

## Documentation

- **[Product Requirements](docs/prd.md)**: Detailed feature specifications and business rules
- **[Contributing](docs/CONTRIBUTING.md)**: Environment setup, development workflow, code quality, testing
- **[Deployment & Operations](docs/deployment.md)**: Docker, staging environments, and the Cloudflare edge maintenance Worker
- **[Production Runbook](deploy/README.md)**: Continuous-deploy pipeline, host setup, rollback
- **[Year-Specific Membership Logic](docs/membership-year-end-logic.md)**: Per-year district/fleet membership system with carry-forward

## Support & Contributing

This project is developed for the International Lightning Class Association. For questions about the G.O.T. Flashes Challenge program, contact the ILCA office.

Developers interested in contributing should read [CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines on code style, testing, and pull request process.

## Acknowledgments

Built with Laravel, hand-authored CSS, and the Lightning Class sailing community in mind.

---

**G.O.T. Flashes**: Get Out There - Let's keep Lightning sailing active and fun!
