# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**G.O.T. Flashes Challenge Tracker** - A Laravel 13 web application for tracking Lightning Class sailing activity. The goal is to encourage sailors to get on the water by recognizing annual sailing days through awards at 10, 25, and 50+ day milestones.

**Key Concept**: "Get Out There - FLASHES" encourages Lightning sailors to get their boats off the dock. Users log sailing days and optional non-sailing days (boat maintenance, race committee work) toward annual awards. Up to 5 non-sailing days count toward award totals per year.

## Essential Commands

### Development Workflow
```bash
# Initial setup (run once)
composer setup                    # Installs dependencies, creates .env, runs migrations

# Start development server
composer dev                      # Runs: Laravel server, queue worker, Pail logs, Vite
# Access at http://localhost:8000

# Code quality (use before committing)
composer check                    # Runs: Pint, PHPStan, ESLint, Stylelint
composer fix                      # Auto-fixes: Pint, ESLint, Stylelint

# Testing
composer test                     # Runs PHPUnit test suite (with APP_ENV=testing)
php artisan test --filter=TestName  # Run specific test

# IMPORTANT: When running tests manually, always use:
APP_ENV=testing php artisan config:clear --ansi && APP_ENV=testing php artisan test
# This ensures proper test environment configuration and avoids CSRF/session issues
```

### Database
```bash
php artisan migrate               # Run migrations
php artisan migrate:fresh         # Fresh DB (destroys all data)
php artisan tinker                # REPL for database interaction
php artisan db:backup             # Backup SQLite DB to storage/app/backups (--no-cleanup skips retention)
```

### Database Backup
- Daily local-disk backup of the SQLite database using SQLite3's native backup API (WAL-mode-aware)
- **Command**: `app/Console/Commands/BackupDatabase.php` (`php artisan db:backup`)
- **Schedule**: `Schedule::command('db:backup')->daily()->at('02:00')` in `routes/console.php`; the Docker `scheduler` process (`docker/supervisord.conf`) runs `schedule:run` every 60 seconds
- **Source**: `database_path('data/database.sqlite')` → **Output**: `storage_path('app/backups')/database-backup-{Y-m-d}.sqlite` (mode `0640`)
- **Validation**: backup reopened read-only and checked with `PRAGMA integrity_check`; invalid backups are deleted and the command exits non-zero
- **Retention**: 90 days (`RETENTION_DAYS`); retention pass also clears orphaned `-wal`/`-shm` files. `--no-cleanup` skips retention
- **Monitoring**: success/failure logged to the `backup` channel (`storage/logs/backup.log`); failures at error level
- Tests: `tests/Feature/BackupDatabaseTest.php`

### Making Admin Users
```bash
php artisan tinker
>>> $user = User::where('email', 'user@example.com')->first();
>>> $user->is_admin = true;
>>> $user->save();
```

## Architecture

### Core Models & Relationships

**User** (`app/Models/User.php`)
- Authenticatable user with Laravel Breeze-style auth
- Fields: first_name, last_name, email, password, date_of_birth, gender, address fields, district, fleet_number, yacht_club
- Relationship: `hasMany(Flash::class)` - one user has many flashes
- Computed attribute: `name` returns "First Last"

**Flash** (`app/Models/Flash.php`)
- Represents a single day's activity logged by a user
- Fields: user_id, date, activity_type, event_type, location, sail_number, notes
- Relationship: `belongsTo(User::class)`
- Key constraint: One flash per user per date (enforced by unique index)
- Method: `isEditable($minDate, $maxDate)` - Determines if flash can be edited/deleted based on grace period logic

### Business Rules

**Activity Types:**
- `sailing` - Always available, counts toward awards (unlimited)
  - Event types: `regatta`, `club_race`, `practice`, `leisure` (displays as "Day Sailing")
- `maintenance` - Boat/trailer work (non-sailing day)
- `race_committee` - Race committee work (non-sailing day)

**Non-Sailing Day Rules:**
- Maximum 5 non-sailing days (maintenance + race committee) count toward awards per calendar year per user
- Users can log more than 5 non-sailing days, but only 5 count toward totals
- No minimum sailing days required to log non-sailing days
- Non-sailing day limit resets annually on January 1st
- Warning message displayed when logging 6th+ non-sailing day (days that don't count toward awards)

**Date Restrictions:**
- Users cannot log future dates (max: today +1 day for timezone handling)
- Cannot duplicate dates (one activity per date per user)
- Current year activities: editable/deletable
- Previous years' activities: read-only after January 31st grace period
- Edit/delete buttons only appear for activities within the editable date range (`Flash::isEditable()`)
- Backend authorization checks (`edit()`, `update()`, `destroy()`) enforce editable date range with 403 responses

**Award Tiers:**
- 10 days = First tier
- 25 days = Second tier
- 50+ days = Third tier (includes Burgee)
- Qualifying days = sailing days + up to 5 non-sailing days

### Authentication Flow

Simple custom auth implementation (not using full Laravel Breeze package):
- Registration: `POST /register` → `App\Http\Controllers\Auth\Register`
- Login: `POST /login` → `App\Http\Controllers\Auth\Login`
- Logout: `POST /logout` → `App\Http\Controllers\Auth\Logout`
- Password hashing via bcrypt (Laravel default)
- Session-based authentication

**Email handling:** Emails are normalized (lowercase + trim) on every write and read path — `User::normalizeEmail()` plus `email`/`pending_email` model mutators, and explicit normalization in `RegistrationForm`/`ProfileForm` (before the uniqueness check) and in the `Login`/`ForgotPassword`/`ResetPassword` lookups. A backfill migration lowercased existing rows. Validation uses `email:strict` (egulias) — the bare `email`/`email:rfc` rule is lenient and accepts `a@b`/`test@localhost`; `strict` rejects those while staying Unicode-aware. `EmailSuggestionService` provides advisory "did you mean …?" domain-typo suggestions via `levenshtein()` against curated **constant** lists (static reference data, not DB — a future DB-derived version is issue #41); wired into the forms through the `SuggestsEmailCorrections` trait. Tests: `tests/Feature/EmailNormalizationTest.php`, `tests/Unit/EmailSuggestionServiceTest.php`, `tests/Feature/EmailSuggestionLivewireTest.php`.

**Session & CSRF lifetime:** Session driver is `database`, `SESSION_LIFETIME` is 120 minutes, and CSRF tokens are tied to the session. A form left open past the session lifetime carries a stale token. Rather than dead-ending on Laravel's generic 419 "Page Expired" screen, `bootstrap/app.php` registers a render callback that converts the resulting `HttpException(419)` (Laravel maps `TokenMismatchException` to this before render callbacks run, so match on status 419) into a recoverable response:
- Standard form posts (login) → redirect back with old input (minus passwords) and a `session('warning')` flash, shown automatically by the layout's toast system; the reloaded page has a fresh token
- AJAX forms (forgot/reset password, which submit via `fetch` + `response.json()`) → JSON 419 with a `message`, surfaced as an error toast by their existing fetch handlers
- Tests: `tests/Feature/Auth/StaleCsrfTest.php` (note: Laravel skips CSRF validation under `runningUnitTests()`, so the test throws the exception via an inline route rather than posting a bad token)

### Authorization

**Policies** (`app/Policies/FlashPolicy.php`):
- Users can only view/edit/delete their own flashes
- Policy methods: `view()`, `update()`, `delete()`
- Registered in `AppServiceProvider`

### Routing Structure

Routes in `routes/web.php`:
- `/` - Home page (public)
- `/register` - Registration form and handler
- `/login` - Login form and handler
- `/logout` - Logout (POST only)
- `/logbook` - Activity logbook (index, store, update, destroy) plus `/logbook/{id}/edit` - auth required
- `/profile` - View/edit profile - auth required
- `/export/user-data` - CSV export of profile + activity - auth required
- `/leaderboard` - Public leaderboard with three tabs: sailor, fleet, district
- `/stats` - Public community statistics page (lightning goal fill-up + D3 charts)
- `/password/*`, `/verify-email/{token}` - Password reset and email verification
- `/admin/fulfillment`, `/admin/sailor-logs` - Award-admin dashboards - auth + `admin` (`is_admin`) required
- `/admin/settings` - Site settings (community goal) - auth + `super_admin` (`is_super_admin`, elevated tier) required

### Frontend Architecture

**Tech Stack:**
- Blade templates (server-rendered)
- Livewire v4 (reactive components for flash form)
- Hand-authored vanilla CSS (framework-free — Tailwind + DaisyUI were removed; markup keeps Tailwind/DaisyUI-style class *names*, but every rule is written by hand in `resources/css/app.css`)
- Vanilla JavaScript (minimal, progressive enhancement)
- Vite for asset bundling

**JavaScript Patterns:**
- Keep JS minimal - this is primarily server-rendered
- Use `const`/`let`, never `var`
- Event listeners wrapped in `DOMContentLoaded`
- Progressive enhancement (form validation, UX improvements)

**Livewire Components:**
- **FlashForm** (`app/Livewire/FlashForm.php`): Activity entry and edit form
  - Dynamically calculates min/max dates on every render (always current) via `DateRangeService`
  - Solves stale date range problem (users leaving page open across grace period boundaries)
  - Supports both create (multi-date) and edit (single-date) modes
  - Pre-fills form data when editing existing flash
  - Uses separate element IDs for create vs edit mode (`activity_type` vs `activity_type_edit`) to prevent getElementById conflicts
  - JavaScript initialization uses `morph.added` hook to ensure elements exist before attaching listeners
- **FlashList** (`app/Livewire/FlashList.php`): Displays user's activity list with pagination
  - Real-time edit and delete functionality
  - Grace period enforcement for edit/delete operations via `DateRangeService`
  - Responds to flash-saved and flash-deleted events
- **ProgressCard** (`app/Livewire/ProgressCard.php`): Shows user's progress toward award tiers
  - Calculates total flashes with non-sailing day cap (5 per year)
  - Displays current progress and next milestone
  - Responds to flash-saved and flash-deleted events for real-time updates
- **Leaderboard** (`app/Livewire/Leaderboard.php`): Public leaderboard with three tabs
  - Sailor, Fleet, and District leaderboards with instant tab switching (no page reload)
  - URL query parameter support via `#[Url]` attribute for bookmarking
  - Pagination resets automatically when switching tabs
  - Uses Livewire pagination theme for consistent styling
- **CommunityStats** (`app/Livewire/CommunityStats.php`): Public `/stats` page
  - Aggregates per year (15-min `Cache::remember`, key `community-stats-{year}`): key counters, monthly/heatmap/event-mix/signups/age/funnel chart data, fun facts
  - Reuses the leaderboard's capped-qualifying-count + membership carry-forward SQL patterns; excludes the sentinel None fleet/district from group counts
  - Chart data flows to D3 (`resources/js/stats-charts.js`) via a JSON script tag on load and the `community-stats-updated` browser event on year change; chart containers sit in `wire:ignore`
  - Lightning goal fill-up hero: `<x-lightning-fill :percentage>` (CSS clip-path keyframe animation, no Alpine — CSP has no `unsafe-eval`); goal stored via `Setting::get("community_goal_{year}")`
  - Every chart has a server-rendered `<details>` data-table twin (accessibility + no hover-gated values)
- **AdminSettings** (`app/Livewire/AdminSettings.php`): `/admin/settings`, extends `AdminComponent`
  - Sets the per-year community goal (`settings` table via `App\Models\Setting` key-value helpers); saving clears that year's stats cache

**Date Picker** (`resources/js/date-picker.js` + pure date math in
`resources/js/utils/calendar.js`) — home-grown, no library (flatpickr was
removed):
- Multi-date toggle selection (create mode) and single date (edit mode); the
  input's data attributes are the whole contract: `data-mode`,
  `data-min-date`, `data-max-date`, `data-existing-dates`,
  `data-default-date` (edit only). Selections sync to Livewire via
  `component.set('dates'/'date', …)`.
- Existing flash dates render as disabled Lightning-logo days; the date being
  edited is exempt. Header has ONE month dropdown listing only the selectable
  months of the whole range, labeled "July 2026" (during the January grace
  period the list spans the year boundary) — no separate year control, no
  dead options. Grid weeks are only generated where the month has days, so no
  hide-extra-weeks pass exists.
- Day cells are real `<button>`s with aria-labels; keyboard support: Enter on
  the input opens with focus in the grid, arrows/Home/End/PageUp/PageDown
  move (clamped to the range), Escape closes, Tab exits to the next field.
  Logged days are `aria-disabled` — focusable and announced ("already
  logged") but not selectable — while out-of-range days are natively
  disabled and skipped.
- **Livewire-proof by construction, NOT by hooks** (the old flatpickr version
  needed `morph.updated` + `requestAnimationFrame` reinit glue):
  - Initialization is lazy — one delegated click/keydown listener on
    `#date-picker` / `#date-picker-single`; nothing to re-run after morphs or
    `wire:navigate`.
  - Data attributes are re-read on EVERY open, so Livewire can morph them
    freely while the picker is closed; `flash-deleted` needs no handler.
  - The calendar is appended to `document.body` only while open, so morph
    never sees it (and it escapes the edit modal's `overflow-y: auto`).
  - `flash-saved` → `picker.clear()` (empty selection + input).
- Styles are the `.date-picker` / `.dp-*` component block in `app.css`
  (in `@layer components`, brand tokens); test handle: `el._datePicker`.

**District/Fleet selects** (home-grown, no library — tom-select was removed):
- **Both are searchable comboboxes** (`resources/js/utils/combobox.js`) —
  a text input + filtered listbox popup enhancing a hidden native
  `<select>`, which stays the single source of truth for value, options,
  and `change` events. District is clearable by design: emptying it lets a
  user who doesn't know their district search across ALL fleets.
- **Options are server-rendered** in the Blade (no API fetch —
  `/api/districts-and-fleets` and `FleetController` were deleted).
  `user-profile-fields.blade.php` queries districts/fleets in a small `@php`
  block (it must stay an anonymous component: `$this` in it binds to the
  calling Livewire form). Cross-field data rides on option attributes:
  `data-district-id` per fleet, `data-none` on the Unaffiliated/None rows;
  `data-allow-empty="true"` on a select makes its empty option a pickable
  row (admin "All Fleets") instead of placeholder-only.
- **Livewire-proof the date-picker way**: the combobox re-reads its options
  from the live `<option>` DOM on EVERY open and appends the listbox to
  `document.body` only while open. Selects sit in `wire:ignore`; glue
  listens for native bubbling `change` events.
- **Glue** (`resources/js/utils/district-fleet-select.js`, used by
  registration + profile): district change clears fleet and narrows its
  options (via the combobox `extraFilter` hook); picking a fleet auto-fills
  its district (silently + explicit Livewire sync); None fleet with blank
  district fills district as None; profile falls back to None for missing
  values, signup keeps placeholders. `resources/js/sailor-logs.js` is the
  admin variant: district and fleet filters simply clear each other.
- **Selection semantics**: typing filters but never destroys the current
  selection; Escape/blur/light-dismiss reverts the text to the selected
  label. Committing an emptied field (Enter/blur) is the one clear gesture.
  Keyboard: arrows move, Enter picks, Escape closes, Tab exits. The active
  (grey) highlight follows the pointer — the selected row keeps only its
  checkmark, so there is never a second grey row. Popups ALWAYS open
  downward (height capped to viewport space; matches the site-wide
  `position-try-fallbacks: none` on native base-select pickers).
- Styles: `.combobox-input` / `.combobox-listbox` / `.combobox-option` block
  in `@layer components` (input clones the `.select` recipe; popup mirrors
  the base-select picker). Test handles: `select._combobox`
  (`setValue`/`clear`/`open`/`getValue`) and the shared Pest helper
  `selectNativeJs()` for native selects.

**CSS (hand-authored, framework-free — `resources/css/app.css`):**

There is NO CSS framework. Tailwind and DaisyUI were removed; `app.css` is the
single, complete source of truth for all styling (~1,900 lines source, ~9 kB
gzipped built). When in doubt about what a class does, read its rule in
`app.css` — do not reason from Tailwind/DaisyUI documentation or memory.

- **Layer model**: `@layer reset, base, components, utilities` — precedence
  comes from that statement, not source order (the utilities block sits before
  components in the file; that changes nothing). Utilities always beat
  components without `!important`.
- **Design tokens** live in `:root` (OKLCH, Lightning Class brand). Always use
  `var(--color-*)` for brand/surface colors — never hardcode oklch/hex values.
- **The utility set is CLOSED.** Markup uses Tailwind-style names (`flex`,
  `mt-4`, `md:grid-cols-2`, `text-base-content/70`), but only utilities
  actually defined in `app.css` exist. There is no JIT: arbitrary values
  (`w-[13px]`), undefined variants (`hover:X`, `sm:X`), or any utility not in
  the file simply do nothing. To use a new utility, add its rule to the
  utilities layer deliberately (escape `:` and `/` in selectors, e.g.
  `.md\:max-w-xs`, `.text-base-content\/70`).
- **Component classes** use DaisyUI-style names (`btn`, `card`, `alert`,
  `modal`, `table`, `badge`, `tooltip`…) but are re-implementations — DaisyUI
  semantics/modifiers no longer apply. If a DaisyUI modifier has no rule in
  `app.css`, it is dead markup, not a feature.
- **Class-usage validator** (`tests/js/css-classes.test.js` +
  `tests/js/utils/css-validator.js`, runs in `npm test` / `composer check`):
  FAILS the build on any class used in Blade/JS that no rule defines; warns
  (informational only) on defined-but-unused rules. Classes applied purely at
  runtime (`classList` toggles, template strings like toast's `alert-${type}`
  or the date picker's day states) go in the test's IGNORE list. This
  replaces Tailwind's `@source inline()` safelist mechanism entirely.
- **Every rule is layered.** There is no vendor CSS and no unlayered
  exception (the last one — tom-select's overrides — died when tom-select was
  replaced by the home-grown combobox). Never add unlayered rules.
- **Stale-build gotcha**: without `public/hot` (i.e., Vite dev server not
  running), pages serve the static bundle in `public/build/` — CSS edits are
  invisible until `npm run build`. Use `composer dev` for HMR, or rebuild
  before visually verifying a CSS change.
- Style notes: floating label form styling (label sits on the border outline,
  `.floating-label-visible`); tooltips use the secondary (lighter blue)
  background; the date-picker calendar uses brand tokens (blue header, white
  text).

### Database Schema

**users table:**
- Authentication: email (unique), password, remember_token
- Personal: first_name, last_name, date_of_birth, gender
- Address: address_line1, address_line2, city, state, zip_code, country
- Sailing: district_id (FK to districts), fleet_id (FK to fleets), yacht_club
- Admin: is_admin (boolean, default false)

**districts table:**
- Lightning Class districts for geographic organization
- Includes the sentinel "Unaffiliated/None" district (`District::NONE_NAME`/`District::noneId()`) — a real row so member affiliations are never null; excluded from the district leaderboard
- Used in leaderboard aggregation

**fleets table:**
- Lightning Class fleets (numbered)
- Includes fleet_number and fleet_name
- Includes the sentinel "None" fleet (`fleet_number` 0, `Fleet::NONE_NUMBER`/`Fleet::noneId()`), selectable alongside ANY district; excluded from the fleet leaderboard; display code renders `fleet_number ?: '—'`
- Used in leaderboard aggregation

**flashes table:**
- user_id (foreign key to users)
- date (date, unique per user via composite index)
- activity_type (enum: sailing, maintenance, race_committee)
- event_type (nullable string - for sailing activities)
- location, sail_number, notes (all nullable/optional)
- Unique constraint: (user_id, date)

**Database:** SQLite with WAL mode for better concurrency

## Code Quality

### Pre-commit Hooks
- Husky automatically runs `composer check` before every commit
- If checks fail, commit is blocked
- Run `composer fix` to auto-fix issues, then commit again

### Quality Commands
- `composer check` - Runs all quality checks (linting + tests)
- `composer fix` - Auto-fixes code style issues
- `composer test` - Runs test suite only

**`composer check` includes:**
- Laravel Pint (PHP formatting)
- PHPStan (static analysis)
- ESLint (JavaScript)
- Stylelint (CSS)
- PHPUnit test suite

### Linting Configuration
- **PHP**: Laravel Pint (PSR-12) + PHPStan (level 5) via Larastan
- **JavaScript**: ESLint with recommended rules
- **CSS**: Stylelint (`stylelint-config-standard`; `.stylelintrc.json` relaxes only the cosmetic rules that fight app.css's compact one-declaration-per-line utility idiom and Tailwind-escaped class names — all error-catching rules stay on)
- **Blade**: blade-formatter for template formatting

### PHPStan Configuration
- Level 5 static analysis
- Analyzes: app/, routes/, database/
- Excludes: database/migrations/
- Memory limit: 512M
- Config: `phpstan.neon`

### GitHub Actions
- Workflow: `.github/workflows/check.yml`
- Runs on: push and PR to main/develop, `v*` tags, and a monthly schedule
- Jobs: `check` (composer check — linting + tests), `docker` (image build + `/up` smoke test), `publish` (GHCR push)
- Docker jobs check out with `lfs: true` — `public/` images are Git LFS and must be real content in the image

## Deployment

Continuous deploy from `main` — full runbook in `deploy/README.md`, operations guide in `docs/deployment.md`:
- CI publishes `ghcr.io/johnhringiv/gotflashes:latest` (+ `:sha-<commit>` per commit) on every push to `main`; a monthly rebuild picks up base-image CVE fixes
- The production host (Synology, behind pfSense/Cloudflare, no inbound access) polls the tag every 5 minutes via `deploy/poll-deploy.sh` and redeploys on digest change; it also self-heals a stopped container and alerts (non-zero exit) when the edge is unreachable
- `deploy/deploy.sh` replaces the single app container and health-gates on `/up` (the entrypoint runs migrations first); deploys are a brief outage covered by the Cloudflare maintenance Worker
- Secrets + host settings live in a host-side env file (`deploy/gotflashes.env.example`); everything else is baked into the image from `docker/.env.docker`
- SQLite DB, logs, and backups persist in `DATA_DIR` bind mounts; rollback = pin a `sha-…` tag on the host, then `git revert` on `main` to make it durable

## Observability

The application includes comprehensive observability features via `ObservabilityServiceProvider` and middleware:

### Proxy & Real Client IP (load-bearing for logging)
Real client IPs in the request/`security` logs below — and Laravel rate-limiting — come from **nginx's realip module keyed on `CF-Connecting-IP`** (`docker/nginx.conf`), which rewrites `REMOTE_ADDR` to the true client before PHP sees it; `$request->ip()` reads that. Request chain in production: **Cloudflare → HAProxy (on pfSense) → nginx → app**. `set_real_ip_from 0.0.0.0/0` is safe *only* because a pfSense firewall rule restricts ingress to Cloudflare IPs, so every request has already passed through Cloudflare (which sets `CF-Connecting-IP` to the true client and is not client-spoofable, unlike the leftmost `X-Forwarded-For`). The firewall rule is the enforcement — if it were removed, `CF-Connecting-IP` would become forgeable. `trustProxies(at: env('TRUSTED_PROXY_IP'))` in `bootstrap/app.php` is **inert** (defense-in-depth only): nginx has already resolved the client IP, so it never matches a proxy peer and does not affect `$request->ip()`. Do not point IP logging or rate-limiting logic at `trustProxies`/`TRUSTED_PROXY_IP`.

### Auth Rate Limiting
Auth endpoints throttle on the real client IP above, via `App\Support\IpRateLimiter` (which hashes any user-supplied key component so entity/accent variants like `josé@x.com`/`jose@x.com` can't collide buckets). Throttle hits are logged to the `security` channel through `App\Support\SecurityLog`:
- **Login** (`Login.php`) — 5 failures/min per email+IP **plus** a coarse 25/15-min per-IP backstop. Counts failures only, cleared on success. Keyed on email+IP (never IP alone) so a shared club/family NAT can't lock out unrelated members; the lockout retry time is computed from only the limiter(s) that actually tripped.
- **Password reset request** (`/password/email`, `ForgotPassword.php`) — 5/hour/IP, counted **only when mail is actually sent** (so broker-throttled/invalid requests don't drain a shared IP's budget), on top of Laravel's per-email 60s broker throttle.
- **Password reset submit** (`/password/reset`, `ResetPassword.php`) — 10/hour/IP (token-guessing defense-in-depth; every submission counts).
- **Registration / verification resend** — pre-existing per-IP/per-user caps (`RegistrationForm`, `EmailVerificationService`).
- **Warn-only mail-volume monitor** (`ObservabilityServiceProvider`) — logs to `security` at ~80/day (Resend free cap = 100/day). Alerts on a distributed drain the per-IP/per-email caps can't catch; deliberately never blocks (a blocking cap would drop legitimate mail).

### Request Logging (`RequestLoggingMiddleware`)
- **All HTTP requests** are logged with structured context:
  - Unique request ID (UUID) for tracing
  - Method, URL, path, IP, user agent
  - User ID, session ID
  - Request/response size, duration, memory usage
  - Filtered sensitive headers (cookies, auth tokens)
- **Livewire-aware**: Automatically extracts component context from Livewire requests:
  - Component name (e.g., `FlashForm`, `Leaderboard`)
  - Method calls (e.g., `save()`, `delete()`, `switchTab()`)
  - Property updates (form field changes)
- **Performance tracking**: Slow requests (>300ms) logged to `performance` channel
- **Request lifecycle**: Both "Request received" and "Request completed" events

### Authentication Logging
- Login success/failure tracked to `security` channel
- User registration events
- Login duration tracking via session timestamps

### Error Tracking
- Uncaught exceptions logged with full context:
  - Exception class, message, file, line
  - Stack trace (limited to 10 frames)
  - User context (user_id, email, IP)
  - Request context (URL, method, user agent, referer)
- Production PHP warnings/notices captured

### Log Channels
- `structured`: Structured JSON logs for all requests/responses
- `security`: Authentication and authorization events
- `performance`: Slow request warnings

### Example: Livewire Observability
When a user edits a flash or switches leaderboard tabs, logs include:
```json
{
  "livewire": {
    "component_name": "flash-form",
    "calls": [{"method": "update", "params": 0}]
  }
}
```

This allows tracking of:
- Flash creation/editing/deletion
- Leaderboard tab switching
- Form field updates
- Component interactions

**All Livewire operations are automatically logged** - no special instrumentation needed in components.

## Implementation Status

**Completed:**
- ✅ User registration and authentication with district/fleet selection
- ✅ Email normalization (case-insensitive lowercase storage) + `email:strict` validation
- ✅ Inline email typo suggestions ("did you mean …?") via `EmailSuggestionService`
- ✅ Flash CRUD (create, read, update, delete)
- ✅ Flash authorization policies
- ✅ Date validation and duplicate prevention
- ✅ Activity ordering by date (newest first)
- ✅ "Just logged" badge for entries created today
- ✅ UI restyled on hand-authored, framework-free vanilla CSS (Tailwind + DaisyUI removed; CSS bundle 169 kB → 63 kB raw, 28 kB → 13 kB gzipped)
- ✅ Vendor-free frontend: flatpickr replaced by a home-grown date picker, tom-select by a home-grown combobox + native selects (JS bundle 177 kB → 80 kB, CSS 50 kB → 42 kB; zero unlayered CSS)
- ✅ Award tier calculations (10, 25, 50 days)
- ✅ Holistic progress bar (0-50+ days with milestone markers and filled circles)
- ✅ Award badge images (got_10_transparent.png, got_25_transparent.png, got_50_transparent.png, burgee_50_transparent.png)
- ✅ Separate earned awards card with gradient background
- ✅ Non-sailing day cap enforcement (5 per year) in all queries
- ✅ Warning toast when logging non-sailing day after reaching 5-day limit
- ✅ Three leaderboards with tabs (renamed "Days Sailed" and "Sailors"):
  - Sailor leaderboard (individual rankings)
  - Fleet leaderboard (aggregated by fleet_number)
  - District leaderboard (aggregated by district)
- ✅ Leaderboard tie-breaking logic:
  1. Total qualifying flashes (primary sort)
  2. Sailing day count (tie-breaker #1 - more sailing days wins)
  3. First entry timestamp (tie-breaker #2 - earliest entry wins)
  4. Alphabetical by name (tie-breaker #3)
- ✅ User highlighting on leaderboards
- ✅ Leaderboard pagination (15 per page)
- ✅ Dashboard with current year progress and earned awards
- ✅ Floating label form styling on registration and flash forms
- ✅ Lightning Class logo on homepage
- ✅ Favicon integration
- ✅ Multi-date flash entry (bulk logging)
- ✅ Grace period enforcement (January allows previous year entries)
- ✅ Full Livewire integration for interactive features (no page reloads):
  - Flash form with real-time validation
  - Flash list with instant edit/delete
  - Progress card with live updates
  - Leaderboard with instant tab switching
- ✅ Award administrator dashboard
  - Status tracking (Earned/Processing/Sent)
  - Batch operations with checkbox selection
  - CSV export for mailing labels
  - Filtering and search capabilities
  - Admin action logging
- ✅ Automated daily SQLite database backups (validated, WAL-aware, 90-day retention, logged to `backup` channel)
- ✅ Public community stats page (`/stats`, issue #33): lightning goal fill-up hero (configurable default goal, site-admin override at `/admin/settings`), key counters, five D3 charts (activity heatmap, flashes over the season, community growth, sailor ages, award funnel), fun facts; undisclosed gender / unknown age redistributed into displayed groups

**Planned:**
- 📋 Historical year views (read-only previous years)
- 📋 Award certificates (downloadable PDFs)
- 📋 Social sharing features

## Key Files

- `docs/prd.md` - Complete product requirements and business rules
- `docs/CONTRIBUTING.md` - Contribution guidelines and branching strategy
- `composer.json` - PHP dependencies and scripts
- `package.json` - Node dependencies and npm scripts
- `phpstan.neon` - PHPStan configuration
- `eslint.config.js` - ESLint configuration
- `.stylelintrc.json` - Stylelint configuration

## Development Notes

### Branching Strategy

**Branch Model**: Two-tier with `main` (production) and `dev` (staging)

**Workflow**:
1. Create feature branches from `dev`: `feature/your-feature-name`
2. Submit PRs to `dev` (squash merge)
3. Release to `main` from `dev` (merge commit with changelog format)

**Commit Messages**:
- Feature branches: Use conventional commits (`feat:`, `fix:`, etc.)
- Dev → main merges: Use changelog format (Added/Changed/Fixed/Technical)

See `docs/CONTRIBUTING.md` for complete branching workflow and merge commit examples.

### Date Validation & Grace Period Logic
Date validation is centralized in `DateRangeService::getAllowedDateRange()` (`app/Services/DateRangeService.php`):
- Returns a tuple `[$minDate, $maxDate]` for consistent date range logic across the app
- **January (grace period)**: Users can log dates from previous year (Jan 1 of previous year through today +1)
- **February onwards**: Users can only log dates from current year (Jan 1 of current year through today +1)
- Min/max dates are passed from Livewire components to frontend via data attributes on the date picker
- This ensures backend validation and frontend UI constraints are always in sync

When implementing year-based features (award tracking, non-sailing day limits):
- Use calendar year (Jan 1 - Dec 31) for activity counting
- Grace period: Users can log previous year until January 31st
- After Jan 31, previous year becomes read-only
- Non-sailing day counter resets January 1st
- Always use `DateRangeService::getAllowedDateRange()` instead of duplicating the logic

### Livewire JavaScript Integration Patterns

**For edit modals and dynamically added elements:**
- Use `Livewire.hook('morph.added')` to detect when new elements are added to the DOM
- Check both `el.id` and `el.querySelector()` to handle parent containers and direct elements
- Wrap initialization in `requestAnimationFrame()` to ensure DOM is fully painted
- Track initialization with flags (e.g., `element._flashFormInitialized`) to prevent duplicate listeners

**Example pattern (flash-form.js):**
```javascript
Livewire.hook('morph.added', ({ el }) => {
    const hasEditForm = el.id === 'activity_type_edit' ||
                       (el.querySelector && el.querySelector('#activity_type_edit'));
    if (hasEditForm) {
        requestAnimationFrame(() => {
            initializeFlashForm();
        });
    }
});
```

**Why `morph.added` vs `morph.updated`:**
- `morph.added`: Fires when NEW elements are added (use for modals/dynamic content)
- `morph.updated`: Fires when EXISTING elements are updated (use for refreshing existing content)
- In production/Docker, timing differences expose race conditions that work fine in dev
- Always test JavaScript initialization in production Docker builds, not just local dev

### Livewire Performance Best Practices

**Use appropriate wire:model modifiers to minimize re-renders (Livewire v4 semantics):**
- `wire:model` - Deferred by default; value is held client-side and synced on the next network request (e.g. a `save()` action). This is the v4 default (v3's `.defer` is no longer needed).
- `wire:model.live` - Network sync on every input event (use sparingly; v4 runs these in parallel for faster typing)
- `wire:model.live.blur` - Network sync when the field loses focus (this is v3's old `.blur` behavior; in v4 the bare `.blur` only controls *client-side* sync timing, so add `.live` when you need the server round-trip)
- `.renderless` modifier - sync without triggering a re-render (avoid if the field's `updated()` hook must update the DOM, e.g. showing/clearing a validation error)

**Example: FlashForm / ProfileForm fields**
```blade
{{-- These fields drive updated() hooks (resetValidation / validateOnly) that need
     the server round-trip AND a re-render, so .live.blur is required in v4: --}}
<input wire:model.live.blur="location" />
<textarea wire:model.live.blur="notes" />
```

**Why this matters:**
- Every Livewire network sync triggers `render()` which may run database queries
- FlashForm's `render()` queries `existingDates` on every render
- FlashForm.updated() runs `resetValidation()`; ProfileForm.updated() runs `validateOnly()` — both need the sync-on-blur to fire, which is why those fields use `.live.blur` rather than the deferred default

**Rule of thumb (v4):**
- Required fields that drive UI logic: `wire:model.live`
- Fields that live-validate or clear errors on blur: `wire:model.live.blur`
- Fields only needed at submit time: plain `wire:model` (deferred)

### Testing Strategy

**Test-Driven Development (TDD):**
- Write tests first for new features when possible
- All existing functionality has test coverage
- Tests run automatically in CI/CD and pre-commit hooks

**Testing Livewire + JavaScript Integration:**

Livewire components that integrate with JavaScript (like the date picker) require a layered testing approach:

**Layer 1: Livewire Data Layer (PHPUnit)** ✅
- Tests the Livewire → JavaScript data contract
- Verifies correct data is passed to view (via `viewData()`)
- Verifies data updates when events fire
- Fast, no browser required

Example tests in `FlashCalendarIntegrationTest`:
- `existingDates` is populated correctly
- Dates update after save/delete events
- HTML contains correct `data-*` attributes
- Date format matches JavaScript expectations (Y-m-d)

**Layer 2: JavaScript Behavior (Pest browser tests, Playwright)** ✅
- Tests actual browser behavior (real clicks, DOM, Livewire round-trips)
- `tests/Browser/JsIntegration/DatePickerTest.php`: calendar opens, multi-date
  select + toggle-deselect, clear-after-save, has-entry lightning days,
  min/max enforcement, grace-period year dropdown, month nav, light dismiss,
  Escape, keyboard navigation, edit-mode single picker
- Note: the browser runs on REAL time while server time is frozen with
  `travelTo()`, so calendar tests navigate explicitly via
  `_datePicker.setView(year, month)` before asserting on specific dates

**Layer 3: Pure date math (Vitest)** ✅
- `tests/js/date-picker.test.js` exercises the real exported functions in
  `resources/js/utils/calendar.js` (grid construction, range clamping,
  existing-entry locking, edit-date exemption) — no DOM, no browser

**Test Organization:**
- **Feature Tests** (`tests/Feature/`): Full HTTP request/response workflows
  - Authentication (registration, login, logout)
  - Flash CRUD operations
  - Flash ordering and "Just logged" badge
  - Flash progress tracking and award calculations
  - Authorization checks
  - Validation rules
  - Leaderboard (sailor, fleet, district tabs)
- **Unit Tests** (`tests/Unit/`): Individual methods/classes
  - Model relationships and attributes
  - Policy authorization logic
  - Business logic in isolation

**Testing Best Practices:**
- Use `RefreshDatabase` trait for clean database state per test
- Test database: In-memory SQLite (faster than disk)
- Factory pattern: `UserFactory`, `FlashFactory` for test data generation
- Descriptive test names: `test_users_can_create_flash_with_minimal_data()`
- Arrange-Act-Assert pattern in all tests

**Current Coverage (by area, not by count):**
- **Auth & account** (`tests/Feature/Auth`, `tests/Feature`): login/logout/remember-me, registration + validation, password reset (throttle, token expiry, end-to-end, case-insensitive lookup), email verification + email-change pending pattern, resend rate limiting
- **Email normalization & suggestions** (`tests/Feature/EmailNormalizationTest`, `tests/Unit/EmailSuggestionServiceTest`, `tests/Feature/EmailSuggestionLivewireTest`): lowercase storage + uniqueness, mixed-case login/reset, `email:strict`, domain-typo suggestions (incl. ccTLD false-positive guards)
- **Logbook / flashes** (`tests/Feature`, `tests/Feature/Livewire`): create (single + multi-date), edit, delete, duplicate prevention, event-type validation, ordering, grace-period boundaries, concurrent/empty-array validation
- **Progress & awards** (`tests/Feature`): tier calculations, non-sailing 5/yr cap, badge thresholds
- **Leaderboards** (`tests/Feature`): sailor/fleet/district, year scoping, tie-breaking, pagination
- **Admin** (`tests/Feature`): award fulfillment + batch ops + CSV, sailor logs filters, role-based access, award-sent email (verified vs unverified, status-change-only)
- **Profile / export** (`tests/Feature`, `tests/Browser/Profile`): profile edit, email change, user-data CSV export
- **Browser / E2E** (`tests/Browser`): full auth, logbook CRUD + grace-period 403s, leaderboard tabs, admin flows, JS integration (date picker, district/fleet combobox, toasts), multi-page flows
- **Unit** (`tests/Unit`): User/Flash/Member models, `FlashPolicy`, `DateRangeService` grace logic
- **JS unit** (`tests/js`, Vitest): CSS class-usage validation (used-but-undefined fails the build, dead CSS warns), combobox widget + district/fleet glue (real components in happy-dom, no mocks), date-picker calendar math
- Known gap: `MailAllowlistProvider` (dev-only mail safety net) has no dedicated test

**Running Tests:**
```bash
composer test      # Run full test suite
composer check     # Run tests + all quality checks
```

### Common Pitfalls
- `@playwright/test` is pinned `~1.61.1`: 1.62.0 hangs pest-plugin-browser at
  boot (client protocol mismatch — the browser suite produces zero output).
  Don't bump past 1.61.x until the plugin catches up, and validate any
  lockfile change with `npx -y npm@latest ci --dry-run` (CI runs npm 12;
  incremental npm-11 lock edits go stale)
- Every class written in Blade/JS must have a rule in `resources/css/app.css` — there is no CSS framework and no JIT. The utility set is closed; the CSS validator test fails `composer check` on undefined classes
- CSS edits are invisible without a rebuild when the Vite dev server isn't running (`public/build/` is stale) — run `npm run build` or use `composer dev`
- Don't forget the unique constraint on (user_id, date) for flashes
- Non-sailing day counting must be year-specific, not all-time, and capped at 5 per year
- Date validation needs timezone tolerance (+1 day max) and grace period enforcement
- Authorization policies must check user ownership
- Previous year data becomes read-only after grace period (February 1st)
- When querying dates, use `DB::raw('DATE(date)')` for proper SQLite date comparison in whereIn clauses
