# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**GOT-FLASHES Challenge Tracker** - A Laravel 12 web application for tracking Lightning Class sailing activity. The goal is to encourage sailors to get on the water by recognizing annual sailing days through awards at 10, 25, and 50+ day milestones.

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
```

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

### Business Rules

**Activity Types:**
- `sailing` - Always available, counts toward awards (unlimited)
- `maintenance` - Boat/trailer work (non-sailing day)
- `race_committee` - Race committee work (non-sailing day)

**Non-Sailing Day Rules:**
- Maximum 5 non-sailing days (maintenance + race committee) count toward awards per calendar year per user
- Users can log more than 5 non-sailing days, but only 5 count toward totals
- No minimum sailing days required to log non-sailing days
- Non-sailing day limit resets annually on January 1st
- UI should indicate when 5 counting non-sailing days have been used

**Date Restrictions:**
- Users cannot log future dates (max: today +1 day for timezone handling)
- Cannot duplicate dates (one activity per date per user)
- Current year activities: editable/deletable
- Previous years' activities: read-only after January 31st grace period

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
- `/flashes` - Resource routes (index, store, edit, update, destroy) - auth required
- `/leaderboard` - Public leaderboard with three tabs: sailor, fleet, district

### Frontend Architecture

**Tech Stack:**
- Blade templates (server-rendered)
- Tailwind CSS v4 (utility-first CSS)
- Vanilla JavaScript (minimal, progressive enhancement)
- Vite for asset bundling

**JavaScript Patterns:**
- Keep JS minimal - this is primarily server-rendered
- Use `const`/`let`, never `var`
- Event listeners wrapped in `DOMContentLoaded`
- Progressive enhancement (form validation, UX improvements)

**CSS/Tailwind:**
- Use Tailwind utility classes first
- Custom CSS only when necessary in `resources/css/app.css`
- Tailwind v4 uses `@source` and `@theme` directives

### Database Schema

**users table:**
- Authentication: email (unique), password, remember_token
- Personal: first_name, last_name, date_of_birth, gender
- Address: address_line1, address_line2, city, state, zip_code, country
- Sailing: district, fleet_number, yacht_club
- Admin: is_admin (boolean, default false)

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
- Memory limit: 256M
- Config: `phpstan.neon`

### GitHub Actions
- Workflow: `.github/workflows/check.yml`
- Runs on: push and PR to main/master/develop branches
- Single job runs `composer check` (linting + tests)

## Implementation Status

**Completed:**
- ✅ User registration and authentication
- ✅ Flash CRUD (create, read, update, delete)
- ✅ Flash authorization policies
- ✅ Date validation and duplicate prevention
- ✅ Activity ordering by date (newest first)
- ✅ "Just logged" badge for entries created today
- ✅ UI with Tailwind CSS and DaisyUI components
- ✅ Award tier calculations (10, 25, 50 days)
- ✅ Progress tracking with visual progress bars
- ✅ Award badges (Bronze/Silver/Gold) with Bootstrap Icons SVG
- ✅ Non-sailing day cap enforcement (5 per year) in all queries
- ✅ Three leaderboards with tabs:
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

**In Progress:**
- 🔄 Year-end rollover logic (grace period until Jan 31)
- 🔄 Non-sailing day limits UI enforcement (show when 5 used)

**Planned:**
- 📋 Award administrator dashboard
- 📋 Historical year views (read-only previous years)
- 📋 CSV export for award fulfillment
- 📋 Award certificates (downloadable PDFs)
- 📋 Social sharing features

## Key Files

- `docs/prd.md` - Complete product requirements and business rules
- `docs/CONTRIBUTING.md` - Contribution guidelines
- `composer.json` - PHP dependencies and scripts
- `package.json` - Node dependencies and npm scripts
- `phpstan.neon` - PHPStan configuration
- `eslint.config.js` - ESLint configuration
- `.stylelintrc.json` - Stylelint configuration

## Development Notes

### Year Calculation Logic
When implementing year-based features (award tracking, non-sailing day limits):
- Use calendar year (Jan 1 - Dec 31) for activity counting
- Grace period: Users can log previous year until January 31st
- After Jan 31, previous year becomes read-only
- Non-sailing day counter resets January 1st

### Testing Strategy

**Test-Driven Development (TDD):**
- Write tests first for new features when possible
- All existing functionality has test coverage
- Tests run automatically in CI/CD and pre-commit hooks

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

**Current Coverage:**
- 95 tests with 275+ assertions
- 100% coverage of existing features
- Authentication, authorization, CRUD, validation, leaderboards, progress tracking all tested

**Running Tests:**
```bash
composer test      # Run full test suite
composer check     # Run tests + all quality checks
```

### Common Pitfalls
- Don't forget the unique constraint on (user_id, date) for flashes
- Non-sailing day counting must be year-specific, not all-time, and capped at 5 per year
- Date validation needs timezone tolerance (+1 day max)
- Authorization policies must check user ownership
- Previous year data becomes read-only after grace period
