# Contributing to G.O.T. Flashes

Thank you for your interest in contributing to the G.O.T. Flashes Challenge Tracker!

## Development Environment Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & NPM
- SQLite
- Git LFS

### Ubuntu/Debian (including WSL)

Install PHP, Composer, and required extensions:
```bash
sudo apt install composer php8.3-xml php8.3-sqlite3 git-lfs
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
- **Testing**: PHPUnit (168+ tests)

### Testing

All new features and bug fixes require tests:

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=FlashTest
```

Tests use in-memory SQLite for speed. Write feature tests for HTTP workflows and unit tests for isolated logic.

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
- Added 4 new test cases (186 tests total)
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