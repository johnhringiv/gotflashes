# G.O.T. Flashes Challenge Tracker

[![codecov](https://codecov.io/gh/johnhringiv/gotflashes/graph/badge.svg)](https://codecov.io/gh/johnhringiv/gotflashes)

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

### Current Implementation
- **Activity Logging**: Log sailing days with details (location, sail number, event type, notes)
- **Multi-Date Selection**: Interactive calendar picker allows logging multiple dates at once
  - Select multiple dates with the same activity details
  - Existing entries marked with lightning logo (cannot be re-selected)
  - Future dates grayed out and disabled
  - Year selector dropdown (shows only allowed years based on grace period)
  - All-or-nothing validation (if any date has an error, no entries are created)
  - Calendar styled with Lightning Class brand colors
  - **Dynamic date ranges**: Livewire automatically refreshes allowed dates if page left open (prevents stale grace period boundaries)
- **Activity Management**: Edit and delete your own activity entries (current year + grace period for previous year in January)
  - Edit mode uses same calendar picker in single-date mode
  - Year dropdown and grace period restrictions apply to both create and edit
- **Profile Management**: Edit your profile information and Lightning Class affiliations
  - Update personal details (name, date of birth, gender)
  - Update mailing address
  - Update email address with verification (pending email pattern - old email stays active until new email verified)
  - Change district and fleet affiliations (updates current year membership)
  - Resend verification or cancel email changes
  - Real-time validation ensures data quality
- **Data Export**: Download complete profile and activity history as CSV with year-appropriate district/fleet data
- **Progress Tracking**: Visual progress bars and award badges (10/25/50-day tiers) on your dashboard
- **Award Fulfillment Dashboard** (Admin only): Manage physical award mailings
  - Track award status: Earned → Processing → Sent
  - Batch operations with checkbox selection
  - Filter by year, status, tier, and search
  - CSV export for mailing labels
  - Discrepancy warnings when users drop below thresholds
  - Flexible status transitions with confirmation modals
  - Admin action logging to dedicated log channel
- **Sailor Activity Logs** (Admin only): View and export all participant activity
  - Filter by year, district, or fleet (mutually exclusive)
  - Search by sailor name or email
  - CSV export with all activity data
  - Audit logging for all exports
- **Year-Specific Memberships**: Track district/fleet affiliations per year with automatic carry-forward (see [membership-year-end-logic.md](docs/membership-year-end-logic.md))
- **Dynamic Fleet Selection**: Real-time fleet lookup based on district during registration
- **Three Leaderboards**:
  - **Sailor**: Individual rankings by total flashes with year-specific memberships
  - **Fleet**: Fleet-level rankings with member counts
  - **District**: District-level rankings with member counts
- **Community Statistics** (Public `/stats`): Class-wide participation dashboard
  - Animated "lightning fill-up" progress toward the year's community goal (a configurable default, overridable per year by a site admin) with a prior-year benchmark line
  - Headline counters plus five self-hosted D3 charts: activity heatmap, flashes over the season (filter by activity type / gender / age, count or share), community growth by age or gender, sailor ages, and a sign-up→award funnel
  - Privacy-preserving: only community-level aggregates are shown, and undisclosed gender / unknown age are redistributed into the displayed groups so no individual can be singled out
  - Fun facts (season opener, longest sailing streak, and more) with all tied sailors credited
  - Every chart has an accessible, hover-free data-table twin; aggregates cached per year for fast public loads
- **User Authentication**: Secure registration, login, password reset, and email verification system
  - Soft email verification - new users can immediately use the app without verifying
  - Persistent verification reminder banner for unverified accounts
  - 24-hour verification token expiration with resend option
  - Email change verification with pending_email pattern
- **Authorization**: Users can only view and modify their own entries
- **Data Integrity**: Prevents duplicate date entries per user
- **Non-Sailing Day Cap**: Automatically caps maintenance and race committee days at 5 per year
- **Date Ordering**: Activities ordered by activity date (newest first)
- **User Highlighting**: Your position highlighted on leaderboards
- **Leaderboard Tie-Breaking**: Advanced ranking logic (total flashes → sailing days → first entry → alphabetical)
- **Responsive Design**: Tailwind CSS responsive UI works on desktop and mobile
- **Self-Hosted**: All assets and dependencies bundled locally (no CDNs)
- **Production Ready**: Docker containerization with optimized builds and caching

### Technical Highlights
- **Secure Authentication**: Laravel's built-in session-based authentication
- **Authorization Policies**: Enforces user ownership of activity records
- **Year-Based Membership System**: Separate memberships table tracks district/fleet affiliations per year
- **Dynamic API Endpoints**: Real-time fleet lookup with 1-hour cache + ETag support
- **Database Constraints**: Unique indexes prevent duplicate entries per user per date/year
- **Date Validation**: Prevents future date entries (with timezone tolerance)
- **Optimized Queries**: Efficient aggregations with proper indexing for leaderboard performance
- **Code Quality**: Automated linting with Laravel Pint, PHPStan, ESLint, and Stylelint
- **Pre-commit Hooks**: Automatically runs code quality checks before commits
- **Comprehensive Testing**: Full test suite covering PHP and JavaScript with TDD practices

## Technology Stack

- **Backend**: Laravel 13 (PHP 8.5+)
- **Database**: SQLite with WAL mode
- **Email**: Resend (transactional email delivery)
  - Email verification for new accounts and email changes
  - Password reset emails
  - 24-hour token expiration for security
- **Frontend**:
  - Tailwind CSS v4 (self-hosted, no CDN)
  - DaisyUI (component library)
  - Blade templates
  - Livewire v4 (reactive components for dynamic date range updates)
  - Vanilla JavaScript with native `fetch()` API
  - Flatpickr (multi-date calendar picker)
  - Tom-Select (searchable dropdowns)
- **Authentication**: Laravel's built-in session-based authentication
- **Asset Bundling**: Vite
- **Deployment**: Docker (Alpine Linux + PHP-FPM + Nginx + Supervisor)

## Getting Started

### Prerequisites
- PHP 8.5+
- Composer
- Node.js & NPM
- SQLite
- Git LFS

See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for detailed environment setup instructions (Ubuntu/WSL, nvm, etc.).

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

### Creating Admin Users

After setup, you'll need to create at least one admin user to access the award fulfillment dashboard:

```bash
php artisan tinker
```

Then in the Tinker REPL:
```php
$user = User::where('email', 'user@example.com')->first();
$user->is_admin = true;        // award administration (fulfillment, sailor logs)
$user->is_super_admin = true;  // elevated site-operator tier (site settings)
$user->save();
```

`is_admin` and `is_super_admin` are independent — grant `is_super_admin` to access `/admin/settings` (the community goal). Press `Ctrl+C` to exit Tinker.

**Security Note:** Only grant admin access to trusted users. Admins can:
- View all user addresses and contact information
- Manage award fulfillment records across all users
- Export user data via CSV (includes personal information)
- Access the award fulfillment dashboard

### Docker Deployment

Production runs the image CI publishes to GHCR on every push to `main`
(`ghcr.io/johnhringiv/gotflashes:latest`); the host polls and redeploys
automatically — **continuous deploy from `main`**. See
**[deploy/README.md](deploy/README.md)** for the full runbook (host setup,
rollback, staging instance).

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

See **[docs/deployment.md](docs/deployment.md)** for the complete operations guide including:
- Configuration and staging environments
- Production deployment behind HAProxy
- Cloudflare edge maintenance Worker
- Management commands
- Troubleshooting

---

## Development Workflow

### Testing

This project follows Test-Driven Development (TDD) practices with comprehensive test coverage.

**Run the test suite:**
```bash
composer test
```

**Test Coverage:**
- Comprehensive test suite covering PHP and JavaScript
- Feature tests: Authentication, CRUD operations, authorization, validation, multi-date selection, leaderboards, progress tracking, navigation, registration with memberships, profile management, email verification
- Unit tests: Models (User, Flash, Member, District, Fleet), policies, business logic
- Livewire tests: FlashForm and ProfileForm components with dynamic date range refresh, grace period boundary crossing, membership updates, email change verification
- Admin dashboard tests: Authorization, award status management, bulk operations, filtering, CSV export
- Email verification tests: Registration verification, email change with pending_email, token expiration, resend/cancel flows
- JavaScript tests: Registration form validation and dynamic fleet selection, multi-date picker logic
- Multi-date picker tests: All-or-nothing validation, duplicate detection, grace period logic
- Uses in-memory SQLite for fast test execution

**Test Organization:**
- `tests/Feature/` - Full HTTP request/response workflows
- `tests/Browser/` - End-to-end browser tests (Pest 4 + Playwright)
- `tests/Unit/` - Individual components in isolation
- `tests/js/` - Vitest JavaScript unit tests
- Feature/Unit tests use `RefreshDatabase`; browser tests use `LazilyRefreshDatabase`

### Code Quality & Linting

This project uses multiple linters and automated tests to maintain code quality. Three simple commands handle everything:

```bash
# Check all code (linting + tests)
composer check

# Run tests only
composer test

# Auto-fix code style issues
composer fix
```

#### What Gets Checked

**`composer check`** runs:
- **Laravel Pint** - PHP code formatting (PSR-12)
- **PHPStan** - PHP static analysis (type safety, bug detection)
- **ESLint** - JavaScript linting
- **Stylelint** - CSS linting
- **PHPUnit** - PHP test suite
- **Vitest** - JavaScript test suite (via npm test)

**`composer fix`** runs:
- **Laravel Pint** - Auto-fixes PHP formatting
- **ESLint** - Auto-fixes JavaScript issues
- **Stylelint** - Auto-fixes CSS issues

#### Pre-commit Hooks

This project uses Husky to automatically run code quality checks before each commit. When you commit, `composer check` runs automatically, which includes:
- Laravel Pint (PHP formatting)
- PHPStan (PHP static analysis)
- ESLint (JavaScript linting)
- Stylelint (CSS linting)
- PHPUnit (test suite)

If any check fails, the commit will be blocked until you run `composer fix` and fix any remaining issues.

## Project Structure

```
gotflashes/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Request handling (Auth, Flash, Leaderboard, Profile, Admin)
│   │   └── Middleware/      # Request middleware
│   ├── Livewire/            # Livewire v4 components (FlashForm, Leaderboard, ProgressCard)
│   ├── Models/              # Eloquent models (User, Flash, Member, District, Fleet)
│   ├── Policies/            # Authorization policies (FlashPolicy)
│   ├── Providers/           # Service providers (AppServiceProvider, ObservabilityServiceProvider)
│   ├── Services/            # Business logic (DateRangeService)
│   └── View/                # View composers
├── bootstrap/               # Laravel bootstrap files
├── config/                  # Configuration files
├── database/
│   ├── factories/           # Model factories for testing
│   ├── migrations/          # Database schema definitions
│   ├── seeders/             # Database seeders
│   └── database.sqlite      # SQLite database file
├── docker/                  # Docker-specific files
├── docs/
│   ├── prd.md              # Product Requirements Document
│   ├── deployment.md       # Deployment & operations (Docker, staging, Cloudflare edge)
│   ├── membership-year-end-logic.md  # Year-specific membership system
│   ├── CONTRIBUTING.md     # Contribution guidelines
│   └── admin-awards-*.md   # Admin dashboard plans
├── workers/                # Cloudflare edge maintenance-page Worker
├── public/                  # Web server document root
│   ├── images/             # Award badges, logo, burgee
│   └── build/              # Compiled assets (via Vite)
├── resources/
│   ├── views/              # Blade templates
│   ├── css/                # Stylesheets (Tailwind CSS)
│   └── js/                 # JavaScript files (multi-date-picker, registration, etc.)
├── routes/
│   └── web.php             # Web routes
├── storage/
│   ├── app/                # Application storage
│   ├── logs/               # Application logs
│   └── framework/          # Framework cache, sessions, views
├── tests/
│   ├── Feature/            # Feature tests (HTTP workflows)
│   ├── Browser/            # End-to-end browser tests (Pest 4 + Playwright)
│   ├── Unit/               # Unit tests (isolated logic)
│   └── js/                 # Vitest JavaScript tests
├── .github/                # GitHub Actions workflows
├── composer.json           # PHP dependencies
├── package.json            # Node dependencies
├── Dockerfile              # Docker image definition
├── phpunit.xml             # PHPUnit configuration
├── phpstan.neon            # PHPStan configuration
├── eslint.config.js        # ESLint configuration
├── vite.config.js          # Vite bundler configuration
└── CLAUDE.md               # AI assistant instructions
```

## Documentation

- **[Product Requirements](docs/prd.md)**: Detailed feature specifications and business rules
- **[Deployment & Operations](docs/deployment.md)**: Docker, staging environments, and the Cloudflare edge maintenance Worker
- **[Year-Specific Membership Logic](docs/membership-year-end-logic.md)**: Per-year district/fleet membership system with carry-forward
- **[Contributing](docs/CONTRIBUTING.md)**: Guidelines for contributing to the project

## Configuration

### Environment Variables

**Required (must be set manually):**
- `APP_KEY` - Application encryption key (auto-generated by `php artisan key:generate`)
- `RESEND_KEY` - Resend API key for sending transactional emails

**Commonly Modified:**
- `APP_ENV` - Environment: `local`, `staging`, or `production`
- `APP_DEBUG` - Debug mode: `true` for development, `false` for production
- `APP_URL` - Your application URL (e.g., `https://gotflashes.com`)
- `START_YEAR` - Application start year for grace period logic (default: `2026`). Grace period (allowing previous year entries in January) only applies **after** this year. Example: With `START_YEAR=2026`, January 2026 only allows 2026 entries; January 2027+ allows previous year entries during grace period.
- `BASIC_AUTH_USERNAME` / `BASIC_AUTH_PASSWORD` - Optional HTTP Basic Auth for staging protection

**Observability (optional tuning):**
- `LOG_SLOW_QUERIES` - Log database queries exceeding threshold (default: enabled)
- `SLOW_QUERY_THRESHOLD_MS` - Slow query threshold in milliseconds (default: 100ms)
- `SLOW_REQUEST_THRESHOLD_MS` - Slow HTTP request threshold in milliseconds (default: 300ms)

**Default Configuration:**
- **Local Development**: See `.env.example` for all available options and defaults
- **Production**: See `docker/.env.docker` for production-optimized defaults

### Observability Features

The application includes comprehensive logging and monitoring:

- **Request Logging**: All HTTP requests with structured context (request ID, user, duration, memory)
- **Livewire Tracking**: Automatic component interaction logging (method calls, property updates)
- **Performance Monitoring**: Slow query and slow request detection (configurable thresholds)
- **Security Auditing**: Authentication events and admin actions logged to dedicated channels
- **Error Tracking**: Exceptions logged with full context (user, request, stack trace)

**Log Channels:**
- `structured` - All requests/responses with JSON context
- `security` - Authentication and authorization events
- `performance` - Slow query/request warnings
- `admin` - Admin action audit trail

Logs are written to `storage/logs/`. During development, view real-time logs with `php artisan pail`.

## Production Deployment

### Docker Deployment

**Required Setup:**
1. Set `APP_KEY` in your environment (generate with `php artisan key:generate`)
2. Set `RESEND_KEY` in your environment (obtain from [resend.com](https://resend.com))
3. Mount persistent storage paths:
   - `/var/www/html/database/data` - SQLite database files (includes WAL files)
   - `/var/www/html/storage/logs` - Application logs

**Production defaults** are pre-configured in `docker/.env.docker`. Only override when needed.

### Deployment Stack

- **Cloudflare**: DNS, CDN, and Email Routing (firewall restricts traffic to Cloudflare IPs only)
- **Cloudflare Worker**: Serves a branded maintenance page from the edge during origin outages (`workers/`; see [docs/deployment.md](docs/deployment.md))
- **Resend**: Transactional email delivery (password resets, award notifications)
- **ACME/Let's Encrypt**: SSL certificate management
- **HAProxy**: SSL termination and reverse proxy
- **Docker Container**: Application (nginx + PHP-FPM + Supervisor)

**Security Note:** Firewall-level restrictions ensure only Cloudflare IPs can reach the server, so nginx's realip module can safely resolve the real client IP from the `CF-Connecting-IP` header (set by Cloudflare, not client-spoofable) for logging and rate-limiting. Laravel's `trustProxies` (`TRUSTED_PROXY_IP`) is inert defense-in-depth — nginx already rewrites `REMOTE_ADDR`, so `$request->ip()` does not depend on it.

See this [guide](https://johnhringiv.com/secure-scalable-home-web-hosting) for the full deployment setup.

## Support & Contributing

This project is developed for the International Lightning Class Association. For questions about the G.O.T. Flashes Challenge program, contact the ILCA office.

Developers interested in contributing should read [CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines on code style, testing, and pull request process.

## Acknowledgments

Built with Laravel, Tailwind CSS, and the Lightning Class sailing community in mind.

---

**G.O.T. Flashes**: Get Out There - Let's keep Lightning sailing active and fun!
