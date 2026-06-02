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
- `/password/*`, `/verify-email/{token}` - Password reset and email verification
- `/admin/fulfillment`, `/admin/sailor-logs` - Admin dashboards - auth + admin required

### Frontend Architecture

**Tech Stack:**
- Blade templates (server-rendered)
- Livewire v3 (reactive components for flash form)
- Tailwind CSS v4 (utility-first CSS)
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

**Multi-Date Picker** (`resources/js/multi-date-picker.js`):
- Uses flatpickr for date selection with multiple date support
- Min/max dates passed from Livewire component via data attributes
- Year selector converted to dropdown (only shows current year + previous year during grace period)
- Custom `hideExtraWeeks()` function removes calendar weeks containing only adjacent month dates
- Existing flash dates are disabled and marked with Lightning logo
- **Livewire Integration Pattern**: Syncs with Livewire updates using hooks
  - Listens for `flash-saved` and `flash-deleted` events to set pending flags
  - Uses Livewire's `morph.updated` hook to detect when date picker element updates
  - Wraps reinitialization in `requestAnimationFrame()` to wait for browser paint cycle
  - Re-queries element with `document.getElementById()` to get freshest DOM reference
  - This ensures flatpickr always has current `data-existing-dates` after Livewire updates
  - **Key insight**: Even after Livewire morph completes, must wait one browser frame for paint cycle to finish before DOM attributes are truly current

**CSS/Tailwind:**
- Use Tailwind utility classes first
- Custom CSS only when necessary in `resources/css/app.css`
- Tailwind v4 uses `@source` and `@theme` directives
- Custom "lightning" theme with Lightning Class brand colors
- Floating label form styling (label appears in border outline)
- Tooltips use lighter blue background (secondary color)
- Flatpickr calendar styled with Lightning Class brand colors (blue header, white text)
- **Dynamic Classes**: Classes created at runtime (e.g., in JavaScript) must be force-included using `@source inline("class-name")` in app.css
  - Example: Toast notification alert variants (`alert-warning`, `alert-error`, etc.) are dynamically created in `toast.js`
  - Without `@source inline()`, Tailwind's JIT compiler won't include these classes in the build
  - See line 66 in `resources/css/app.css` for the toast alert safelist

### Database Schema

**users table:**
- Authentication: email (unique), password, remember_token
- Personal: first_name, last_name, date_of_birth, gender
- Address: address_line1, address_line2, city, state, zip_code, country
- Sailing: district_id (FK to districts), fleet_id (FK to fleets), yacht_club
- Admin: is_admin (boolean, default false)

**districts table:**
- Lightning Class districts for geographic organization
- Used in leaderboard aggregation

**fleets table:**
- Lightning Class fleets (numbered)
- Includes fleet_number and fleet_name
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
- **CSS**: Stylelint with Tailwind CSS support
- **Blade**: blade-formatter for template formatting

### PHPStan Configuration
- Level 5 static analysis
- Analyzes: app/, routes/, database/
- Excludes: database/migrations/
- Memory limit: 512M
- Config: `phpstan.neon`

### GitHub Actions
- Workflow: `.github/workflows/check.yml`
- Runs on: push and PR to main/master/develop branches
- Single job runs `composer check` (linting + tests)

## Observability

The application includes comprehensive observability features via `ObservabilityServiceProvider` and middleware:

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
- ✅ Flash CRUD (create, read, update, delete)
- ✅ Flash authorization policies
- ✅ Date validation and duplicate prevention
- ✅ Activity ordering by date (newest first)
- ✅ "Just logged" badge for entries created today
- ✅ UI with Tailwind CSS v4 and DaisyUI components
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

**Use appropriate wire:model modifiers to minimize re-renders:**
- `wire:model.defer` - Syncs on form submission (best for forms with many fields)
- `wire:model.blur` - Syncs when field loses focus (good for text inputs, prevents query-per-keystroke)
- `wire:model.live` - Syncs on every keystroke (use sparingly, only when real-time updates needed)
- `wire:model` - Syncs on input event (avoid for text fields, causes unnecessary renders)

**Example: FlashForm optimization**
```blade
{{-- GOOD: Only syncs when user leaves field --}}
<input wire:model.blur="location" />
<textarea wire:model.blur="notes" />

{{-- BAD: Syncs on every keystroke, triggers render() + DB queries --}}
<input wire:model="location" />
```

**Why this matters:**
- Every Livewire sync triggers `render()` which may run database queries
- FlashForm's `render()` queries `existingDates` on every render
- Using `wire:model` on a notes textarea = database query per keystroke
- Using `wire:model.blur` = database query only when field loses focus

**Rule of thumb:**
- Required fields that drive UI logic: `wire:model.live` or `wire:model`
- Optional text fields: `wire:model.blur`
- Form submission: `wire:model.defer`

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

**Layer 2: JavaScript Behavior (Laravel Dusk)** ⏸️
- Tests actual browser behavior (flatpickr, DOM manipulation)
- Verifies dates are disabled/enabled in UI
- Verifies JavaScript receives and processes Livewire updates
- Slow, requires browser automation

Example tests in `FlashCalendarTest` (Dusk, optional):
- Clicking date in calendar
- Dates become disabled after saving
- Calendar updates without page reload
- Visual indicators appear correctly

**Current Coverage:**
- ✅ Layer 1 (Livewire data) - Fully tested with 6 comprehensive tests
- ⏸️ Layer 2 (JavaScript) - Manual testing currently (Dusk setup optional)

**Why This Works:**
- Layer 1 tests catch 90% of integration bugs (data not updating, wrong format, missing attributes)
- Livewire guarantees if data is passed correctly, JavaScript will receive it
- Manual testing can verify the final 10% (visual behavior, edge cases)

**Testing Confidence:**
If Layer 1 tests pass, you can be confident:
- ✅ Calendar receives fresh data after saves/deletes
- ✅ Dates are in correct format for JavaScript
- ✅ Data attributes are present in HTML
- ✅ Livewire events trigger re-renders

The only thing not tested: flatpickr actually using the data (which is flatpickr's responsibility, not ours).

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
- **Auth & account** (`tests/Feature/Auth`, `tests/Feature`): login/logout/remember-me, registration + validation, password reset (throttle, token expiry, end-to-end), email verification + email-change pending pattern, resend rate limiting
- **Logbook / flashes** (`tests/Feature`, `tests/Feature/Livewire`): create (single + multi-date), edit, delete, duplicate prevention, event-type validation, ordering, grace-period boundaries, concurrent/empty-array validation
- **Progress & awards** (`tests/Feature`): tier calculations, non-sailing 5/yr cap, badge thresholds
- **Leaderboards** (`tests/Feature`): sailor/fleet/district, year scoping, tie-breaking, pagination
- **Admin** (`tests/Feature`): award fulfillment + batch ops + CSV, sailor logs filters, role-based access, award-sent email (verified vs unverified, status-change-only)
- **Profile / export** (`tests/Feature`, `tests/Browser/Profile`): profile edit, email change, user-data CSV export
- **Browser / E2E** (`tests/Browser`): full auth, logbook CRUD + grace-period 403s, leaderboard tabs, admin flows, JS integration (flatpickr, TomSelect, toasts), multi-page flows
- **Unit** (`tests/Unit`): User/Flash/Member models, `FlashPolicy`, `DateRangeService` grace logic
- Known gap: `MailAllowlistProvider` (dev-only mail safety net) has no dedicated test

**Running Tests:**
```bash
composer test      # Run full test suite
composer check     # Run tests + all quality checks
```

### Common Pitfalls
- Don't forget the unique constraint on (user_id, date) for flashes
- Non-sailing day counting must be year-specific, not all-time, and capped at 5 per year
- Date validation needs timezone tolerance (+1 day max) and grace period enforcement
- Authorization policies must check user ownership
- Previous year data becomes read-only after grace period (February 1st)
- When querying dates, use `DB::raw('DATE(date)')` for proper SQLite date comparison in whereIn clauses
