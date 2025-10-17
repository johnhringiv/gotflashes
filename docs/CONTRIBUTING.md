# Contributing to GOT-FLASHES Challenge Tracker

Thank you for your interest in contributing to the GOT-FLASHES Challenge Tracker! This document provides guidelines and instructions for contributing to the project.

## Table of Contents
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Commit Message Guidelines](#commit-message-guidelines)
- [Pull Request Process](#pull-request-process)
- [Database Changes](#database-changes)

## Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & NPM
- SQLite
- Git

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/gotflashes.git
   cd gotflashes
   ```

3. Add the upstream repository:
   ```bash
   git remote add upstream https://github.com/ORIGINAL-OWNER/gotflashes.git
   ```

### Setup Development Environment

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```

2. Setup environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Note: The default `.env` configuration works for local development. No additional environment variables need to be configured.

3. Setup database:
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

4. Start the development server:
   ```bash
   composer run dev
   ```

   This runs multiple services concurrently:
   - Laravel development server (http://localhost:8000)
   - Queue worker
   - Log viewer (Pail)
   - Vite dev server for hot module reload

## Development Workflow

### Branch Strategy

- `main`: Production-ready code
- Feature branches: Created from and merged back into `main`

### Creating a Feature Branch

```bash
# Update your local main branch
git checkout main
git pull upstream main

# Create a new feature branch
git checkout -b feature/your-feature-name
```

Use descriptive branch names:
- `feature/leaderboard-implementation`
- `fix/freebie-calculation-bug`
- `refactor/user-model-cleanup`
- `docs/update-readme`

### Keeping Your Branch Updated

```bash
# Fetch latest changes from upstream
git fetch upstream

# Rebase your branch on latest main
git rebase upstream/main
```

## Coding Standards

This project uses multiple linters and code quality tools to maintain consistency and catch errors early. **Pre-commit hooks will automatically run these tools** on staged files, but you can also run them manually.

### Quick Commands

```bash
# Check all code quality (PHP, JavaScript, CSS)
composer check

# Auto-fix all fixable issues
composer fix
```

### What Gets Checked

**`composer check`** runs:
1. Clears Laravel config cache
2. **Laravel Pint** - Checks PHP formatting (PSR-12)
3. **PHPStan (Larastan)** - Static analysis for type safety and bugs
4. **ESLint** - JavaScript linting
5. **Stylelint** - CSS linting

**`composer fix`** runs:
1. **Laravel Pint** - Auto-fixes PHP formatting
2. **ESLint** - Auto-fixes JavaScript issues
3. **Stylelint** - Auto-fixes CSS issues

### Running Tools Individually

**Laravel Pint** - PSR-12 compliant code formatting:
```bash
# Format all PHP files
./vendor/bin/pint

# Check without modifying files
./vendor/bin/pint --test

# Format specific file
./vendor/bin/pint app/Models/Flash.php
```

**PHPStan (Larastan)** - Static analysis to catch bugs and type errors:
```bash
# Run static analysis
./vendor/bin/phpstan analyse --memory-limit=256M
```

PHPStan is configured at level 5 (see `phpstan.neon`). It analyzes `app/`, `routes/`, and `database/` directories.

### Key Style Guidelines

- **PSR-12 compliance** (enforced by Pint)
- **Type hints**: Use type hints for parameters and return types
  ```php
  public function getUserFlashes(User $user): Collection
  {
      return $user->flashes()->get();
  }
  ```
- **Eloquent conventions**: Follow Laravel naming conventions
  - Model names: Singular, PascalCase (`Flash`, `User`)
  - Table names: Plural, snake_case (`flashes`, `users`)
  - Relationships: Use appropriate method names (`user()`, `flashes()`)

- **Dependency Injection**: Use constructor injection for dependencies
  ```php
  public function __construct(
      private FlashRepository $flashRepository
  ) {}
  ```

### JavaScript & CSS Style Guidelines

**JavaScript:**
- Use ES6+ syntax
- Prefer `const` and `let` over `var`
- Use semicolons
- Keep JavaScript minimal - this is primarily a server-rendered app
- ESLint will catch common issues and enforce consistency

**CSS/Tailwind:**
- Use Tailwind CSS utility classes when possible
- Custom CSS should go in `resources/css/app.css`
- Follow Tailwind's utility-first approach
- Only add custom classes when necessary
- Stylelint will enforce CSS best practices

**Blade Templates:**
- Use Blade components where appropriate
- Follow indentation consistency (4 spaces)
- Use `{{ }}` for escaped output (default)
- Use `{!! !!}` only when absolutely necessary for HTML output
- Blade Formatter will automatically sort Tailwind classes

### Pre-commit Hooks

This project uses **Husky** to automatically run code quality checks before each commit.

When you run `git commit`, the following happens automatically:
1. **`composer check` runs** - Checks all code (PHP, JavaScript, CSS)
2. **If checks pass** - Commit succeeds
3. **If checks fail** - Commit is blocked

To fix failed checks:
```bash
# Auto-fix what can be fixed
composer fix

# Check again
composer check

# Then commit
git commit -m "your message"
```

**Tip:** Run `composer check` before committing to catch issues early.

**Skip the hook?** Use `git commit --no-verify` (not recommended).

## Testing Guidelines

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/FlashTest.php

# Run with coverage
php artisan test --coverage
```

### Writing Tests

**Feature Tests** (`tests/Feature/`):
- Test complete features from HTTP request to response
- Test user workflows and business logic
- Example: Creating a flash, viewing dashboard, user registration

**Unit Tests** (`tests/Unit/`):
- Test individual methods and classes in isolation
- Test models, services, helpers
- Example: Flash validation, date calculations

**Test Structure:**
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FlashTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_flash(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/flashes', [
            'date' => '2025-10-15',
            'activity_type' => 'sailing',
            'location' => 'Lake Test',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('flashes', [
            'user_id' => $user->id,
            'date' => '2025-10-15',
        ]);
    }
}
```

### Test Requirements

- All new features must include tests
- Bug fixes should include regression tests
- Aim for meaningful test coverage, not just high percentages
- Tests should be readable and maintainable

## Commit Message Guidelines

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code refactoring (no functional changes)
- `test`: Adding or updating tests
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic changes)
- `chore`: Maintenance tasks, dependency updates

### Examples

```
feat(flashes): add freebie day limit enforcement

Implement logic to prevent users from logging more than 5 freebie
days per calendar year. Update form to hide freebie options when
limit is reached.

Closes #42
```

```
fix(dashboard): correct year-end rollover calculation

Fixed bug where activities from January of previous year were
incorrectly counted in current year totals due to timezone issue.

Fixes #58
```

```
docs(readme): update installation instructions

Add troubleshooting section and clarify SQLite setup steps.
```

### Guidelines

- Use present tense ("add feature" not "added feature")
- Use imperative mood ("move cursor to..." not "moves cursor to...")
- Limit subject line to 50 characters
- Capitalize subject line
- Don't end subject line with a period
- Separate subject from body with blank line
- Wrap body at 72 characters
- Reference issues and pull requests in footer

## Pull Request Process

### Before Submitting

1. **Ensure tests pass:**
   ```bash
   composer test
   ```

2. **Run all linters:**
   ```bash
   composer check
   ```

   **Note:** Pre-commit hooks will automatically run linters on staged files, but running them manually first can catch issues earlier.

3. **Update documentation** if you changed functionality

4. **Rebase on latest main branch:**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

### Submitting a Pull Request

1. Push your branch to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

2. Go to GitHub and create a Pull Request against the `main` branch

3. Fill out the PR template with:
   - **Description**: What does this PR do?
   - **Motivation**: Why is this change needed?
   - **Testing**: How was this tested?
   - **Screenshots**: If UI changes, include before/after screenshots
   - **Related Issues**: Link to relevant issues

### PR Title Format

```
feat: Add leaderboard filtering by district
fix: Correct freebie day calculation bug
docs: Update contributing guidelines
```

### Review Process

- At least one approval required before merging
- Address review comments promptly
- Keep discussions respectful and constructive
- Be open to feedback and alternative approaches

### After Approval

- Maintainer will merge the PR
- Your feature branch will be deleted after merge
- Update your local repository:
  ```bash
  git checkout main
  git pull upstream main
  git branch -d feature/your-feature-name
  ```

## Database Changes

### Creating Migrations

```bash
php artisan make:migration create_table_name
# or
php artisan make:migration add_column_to_table_name
```

### Migration Guidelines

- **Always review** generated migrations before committing
- **Use descriptive names** that explain the change
- **Include rollback logic** in `down()` method
- **Test migrations** both up and down
- **Never modify** existing migrations that have been deployed

### Example Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

## Questions and Support

- **General questions**: Open a GitHub Discussion
- **Bug reports**: Open a GitHub Issue with detailed reproduction steps
- **Feature requests**: Open a GitHub Issue with clear use case description
- **Security issues**: Email security contact directly (not public issues)

## Recognition

Contributors will be recognized in:
- GitHub contributor list
- Release notes for significant contributions
- Project acknowledgments

Thank you for contributing to the GOT-FLASHES Challenge Tracker and supporting the Lightning Class sailing community!
