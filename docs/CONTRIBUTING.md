# Contributing to G.O.T. Flashes

Thank you for your interest in contributing to the G.O.T. Flashes Challenge Tracker!

## Quick Start

```bash
# Fork and clone the repo
git clone https://github.com/YOUR-USERNAME/gotflashes.git
cd gotflashes

# Setup everything
composer setup

# Start development
composer dev
```

Access the app at http://localhost:8000

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

## Pull Request Process

1. **Create feature branch** from `main`
2. **Write tests** for your changes
3. **Run checks**: `composer check`
4. **Submit PR** with clear description
5. **Address reviews** promptly

### Commit Messages

Use conventional commits:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests only
- `refactor:` Code restructuring

Example: `feat: add fleet-based leaderboard filtering`

## Key Guidelines

- Follow Laravel conventions
- Keep PRs focused and small
- Test database changes both up and down
- Update documentation if needed
- Be respectful in discussions

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