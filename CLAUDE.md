# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**GOT-FLASHES Challenge Tracker** - A Laravel 12 web application for tracking Lightning Class sailing activity. The goal is to encourage sailors to get on the water by recognizing annual sailing days through awards at 10, 25, and 50+ day milestones.

**Key Concept**: "Get Out There - FLASHES" encourages Lightning sailors to get their boats off the dock. Users log sailing days and optional "freebie" days (boat maintenance, race committee work) toward annual awards.

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
composer test                     # Runs PHPUnit test suite
php artisan test --filter=TestName  # Run specific test
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
- `sailing` - Always available, counts toward awards
- `maintenance` - Boat/trailer work (freebie)
- `race_committee` - Race committee work (freebie)

**Freebie Rules:**
- Maximum 5 freebie days per calendar year per user
- No minimum sailing days required to use freebies
- Freebies reset annually on January 1st
- UI should hide freebie options when 5 freebies used

**Date Restrictions:**
- Users cannot log future dates (max: today +1 day for timezone handling)
- Cannot duplicate dates (one activity per date per user)
- Current year activities: editable/deletable
- Previous years' activities: read-only after January 31st grace period

**Award Tiers:**
- 10 days = First tier
- 25 days = Second tier
- 50+ days = Third tier (includes Burgee)
- Qualifying days = sailing days + freebie days

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
- ✅ Basic Flash CRUD (create, read, update, delete)
- ✅ Flash authorization policies
- ✅ Date validation and duplicate prevention
- ✅ Basic UI with Tailwind CSS

**In Progress:**
- 🔄 Award tier calculations and progress tracking
- 🔄 Freebie day limits enforcement (5 per year)
- 🔄 Dashboard with metrics
- 🔄 Year-end rollover logic (grace period until Jan 31)

**Planned:**
- 📋 Leaderboards (individual, fleet, district)
- 📋 Award administrator dashboard
- 📋 Historical year views (read-only previous years)
- 📋 CSV export for award fulfillment

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
When implementing year-based features (award tracking, freebie limits):
- Use calendar year (Jan 1 - Dec 31) for activity counting
- Grace period: Users can log previous year until January 31st
- After Jan 31, previous year becomes read-only
- Freebie counter resets January 1st

### Testing Strategy

**Test-Driven Development (TDD):**
- Write tests first for new features when possible
- All existing functionality has test coverage
- Tests run automatically in CI/CD and pre-commit hooks

**Test Organization:**
- **Feature Tests** (`tests/Feature/`): Full HTTP request/response workflows
  - Authentication (registration, login, logout)
  - Flash CRUD operations
  - Authorization checks
  - Validation rules
- **Unit Tests** (`tests/Unit/`): Individual methods/classes
  - Model relationships and attributes
  - Policy authorization logic
  - Business logic in isolation

**Testing Best Practices:**
- Use `RefreshDatabase` trait for clean database state per test
- Test database: In-memory SQLite (faster than disk)
- Factory pattern: `UserFactory` for test data generation
- Descriptive test names: `test_users_can_create_flash_with_minimal_data()`
- Arrange-Act-Assert pattern in all tests

**Current Coverage:**
- 70 tests with 196 assertions
- 100% coverage of existing features
- Authentication, authorization, CRUD, validation all tested

**Running Tests:**
```bash
composer test      # Run full test suite
composer check     # Run tests + all quality checks
```

### Common Pitfalls
- Don't forget the unique constraint on (user_id, date) for flashes
- Freebie counting must be year-specific, not all-time
- Date validation needs timezone tolerance (+1 day max)
- Authorization policies must check user ownership
- Previous year data becomes read-only after grace period
