# Contributing to G.O.T. Flashes

Thank you for your interest in contributing to the G.O.T. Flashes Challenge Tracker!

## Development Environment Setup

### Prerequisites

- PHP 8.5 or higher
- Composer
- Node.js & NPM
- SQLite
- Git LFS

### Ubuntu/Debian (including WSL)

PHP 8.5 is not in the standard Ubuntu repos (Noble 24.04 ships 8.3), so add the
[ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php) PPA, then install PHP, Composer, and required extensions:
```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.5-cli php8.5-mbstring php8.5-sqlite3 php8.5-xml \
  php8.5-curl composer git-lfs
```
If you have more than one PHP version installed, make 8.5 the default:
```bash
sudo update-alternatives --set php /usr/bin/php8.5
```

### Node.js via nvm (recommended)

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install 20
```

## Quick Start

```bash
# Fork and clone the repo
git clone https://github.com/YOUR-USERNAME/gotflashes.git
cd gotflashes

# Initialize Git LFS and pull assets
git lfs install
git lfs pull

# Create the database
mkdir -p database/data && touch database/data/database.sqlite

# Setup everything
composer setup

# Start development
composer dev
```

Access the app at http://localhost:8000

**Note**: This project uses Git LFS (Large File Storage) for binary assets like images and badges. You must have Git LFS installed and run `git lfs pull` after cloning.

## Development Workflow

### Before Committing

```bash
# Run all checks (required - enforced by pre-commit hooks)
composer check

# Auto-fix code style issues
composer fix

# Run tests only
composer test
```

The project uses automated pre-commit hooks via Husky. If checks fail, your commit will be blocked until issues are fixed.

### Code Quality Tools

- **PHP**: Laravel Pint (PSR-12) + PHPStan level 5
- **JavaScript**: ESLint
- **CSS**: Stylelint
- **Testing**: PHPUnit (feature + unit) and Pest 4 browser tests

### Testing

All new features and bug fixes require tests:

```bash
# Run PHPUnit tests (feature + unit)
php artisan test

# Run browser tests (Pest 4 + Playwright)
./vendor/bin/pest tests/Browser
./vendor/bin/pest tests/Browser --parallel    # ~40s with parallel

# Run a specific test
php artisan test --filter=FlashTest
./vendor/bin/pest tests/Browser/Logbook/CreateTest.php
```

PHPUnit tests use in-memory SQLite. Browser tests use Pest 4's in-process server with `LazilyRefreshDatabase`.

### Test Data Seeding

The `e2e:seed` command creates test users and data for local development and browser tests. It only runs in `local` and `testing` environments.

```bash
# Seed canonical test users (regular, admin, fresh, unverified)
php artisan e2e:seed --scenario=base

# Other scenarios
php artisan e2e:seed --scenario=tiered          # Users at 9, 10, 24, 25, 49, 50 days
php artisan e2e:seed --scenario=leaderboard     # 17 users across districts/fleets
php artisan e2e:seed --scenario=non-sailing-cap # User at the 5-day non-sailing cap
php artisan e2e:seed --scenario=tied-ranks      # Users with identical totals for tie-breaking
php artisan e2e:seed --scenario=many-flashes    # Regular user with 18 flashes

# Reset DB and seed fresh
php artisan e2e:seed --scenario=base --reset
```

**Canonical test users** (all use password `Password123!`):

| Email | Role |
|---|---|
| `delivered+regular@resend.dev` | Regular user with district/fleet |
| `delivered+admin@resend.dev` | Admin user |
| `delivered+fresh@resend.dev` | No flashes, no affiliations |
| `delivered+unverified@resend.dev` | Unverified email |

### Email in Development (Mail Allowlist)

To prevent accidentally emailing real addresses from dev/staging, `MailAllowlistProvider`
intercepts every outgoing message and **silently drops** any whose recipient domain is not
on an allowlist (blocked sends are logged to the `security` channel). This is a development
safety net only — it is not a product feature.

- **Enabled by default in non-production** (`local`, `staging`, `testing`); **off in production**.
- Override with `MAIL_ALLOWLIST_ENABLED` (`true`/`false`). An empty/unset value keeps the
  default (enforce outside production).
- `MAIL_ALLOWED_DOMAINS` is a comma-separated domain list; it defaults to `resend.dev`, which
  is why the canonical test users above use `@resend.dev` addresses (their mail is delivered).

To send to a different domain locally, add it to `MAIL_ALLOWED_DOMAINS`, or set
`MAIL_ALLOWLIST_ENABLED=false` to disable the guard entirely.

## Branching Strategy

We use a two-tier branching model:

### Branch Structure

- **`main`** - Production-ready code (protected)
- **`dev`** - Integration branch for staging (protected)
- **`feature/*`** - Feature branches for development

### Workflow

1. **Create feature branch** from `dev`:
   ```bash
   git checkout dev
   git pull origin dev
   git checkout -b feature/your-feature-name
   ```

2. **Develop and commit** on your feature branch:
   - Make atomic commits with clear messages
   - Run `composer check` before each commit (enforced by hooks)
   - Write tests for all changes

3. **Submit PR to `dev`**:
   - Create PR targeting `dev` branch
   - Feature branches are **squashed** when merged into `dev`
   - Single squash commit represents the entire feature

4. **Release to `main`**:
   - PRs from `dev` to `main` use **merge commits** (no squash)
   - Preserves full commit history from dev
   - Merge commit message must be in changelog format (see below)

### Commit Messages

**For feature branch commits** (will be squashed), use conventional commits:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests only
- `refactor:` Code restructuring
- `chore:` Maintenance tasks

Example: `feat: add fleet-based leaderboard filtering`

**For dev → main merge commits**, use changelog format:

```
Release: [Brief description of release]

Added:
- New feature descriptions
- Another new feature

Changed:
- Improvements to existing features
- Updates to behavior

Fixed:
- Bug fix descriptions
- Another bug fix

Technical:
- Performance improvements
- Dependency updates
- Infrastructure changes
```

Example:
```
Release: Multi-date entry and performance improvements

Added:
- Multi-date flash entry with calendar picker
- Grace period enforcement for January entries

Changed:
- Improved duplicate date checking performance

Fixed:
- Concurrent submission handling
- Grace period boundary validation

Technical:
- Optimized database queries with proper date filtering
- Expanded test coverage for multi-date entry and grace-period boundaries
```

## Pull Request Process

1. **Create feature branch** from `dev`
2. **Write tests** for your changes
3. **Run checks**: `composer check`
4. **Submit PR to `dev`** with clear description
5. **Address reviews** promptly
6. **Squash merge** into `dev` when approved
7. **Merge commit** from `dev` to `main` for releases

## Key Guidelines

- Follow Laravel conventions
- Keep PRs focused and small
- Test database changes both up and down
- Update documentation if needed
- Be respectful in discussions

### Binary Assets & Git LFS

This project uses **Git LFS** for managing binary files:

- ✅ **All binary assets go through LFS** - Images, fonts, PDFs, etc.
- ✅ **Track new file types** in `.gitattributes` if needed
- ❌ **Don't commit large binaries directly** - They must be LFS-tracked

### Frontend Dependencies

**Self-Hosted Philosophy**: This project prioritizes self-hosted assets for performance, privacy, and reliability.

- ✅ **Use npm packages** - All dependencies should be installed via npm and bundled with Vite
- ✅ **Use native browser APIs** - Prefer `fetch()`, Web APIs over libraries when possible
- ❌ **No CDNs** - Do not add external CDN links (jsdelivr, unpkg, cdnjs, Google Fonts, etc.)
- ❌ **No external fonts** - Use system fonts or self-hosted font files only

## Questions?

- Bug reports: Open a GitHub Issue
- Feature requests: Open a GitHub Issue with use case
- Security issues: Contact maintainers directly

Thank you for contributing!