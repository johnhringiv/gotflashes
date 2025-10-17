# GOT-FLASHES Challenge Tracker

A web application for tracking Lightning Class sailing activity and managing the GOT-FLASHES Challenge awards program. This system helps Lightning sailors log their days on the water, track progress toward annual awards, and foster friendly competition within the sailing community.

**GOT-FLASHES** stands for "**Get Out There** - FLASHES" - encouraging Lightning sailors to get their boats off the dock and onto the water!

## About the GOT-FLASHES Challenge

The GOT-FLASHES Challenge encourages Lightning Class sailors to get on the water more frequently by recognizing their annual sailing activity. Participants earn awards at 10, 25, and 50+ days, with the simple goal: **Get the boat off the dock!**

### What Counts
- **Sailing Days**: Any time spent sailing on a Lightning (as skipper or crew)
- **Freebie Days**: Up to 5 days per year for boat/trailer maintenance or race committee work
- **One hour counts as a full day** - we just want you sailing!

### Award Tiers
- **10 Days**: First tier recognition
- **25 Days**: Second tier recognition
- **50+ Days**: Third tier recognition (including Burgee eligibility)

## Key Features

### For Participants
- **Simple Activity Logging**: Log sailing days and optional details (location, sail number, notes)
- **Progress Tracking**: Visual indicators showing advancement toward each award tier
- **Freebie Management**: Track remaining freebie slots (5 per year maximum)
- **Historical Records**: View all past activity with year-by-year summaries
- **Leaderboards**: Compare your performance with other sailors, fleets, and districts
- **Profile Management**: Update your yacht club, fleet, district, and contact information

### For Award Administrators
- **Award Tracking**: View participants who have reached award thresholds
- **Mailing Information**: Access participant contact details for award fulfillment
- **Export Capabilities**: Download award-eligible participants for mail merge

### Technical Highlights
- **Secure Authentication**: Laravel Breeze-based registration and login
- **Data Integrity**: Prevents duplicate entries, enforces freebie limits, validates dates
- **Year-End Rollover**: Automatic reset with grace period for late entries (until January 31st)
- **Responsive Design**: Tailwind CSS responsive UI works on desktop and mobile
- **Self-Hosted**: SQLite database with no external dependencies

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Database**: SQLite with WAL mode
- **Frontend**: Tailwind CSS v4, Blade templates, Vanilla JavaScript
- **Authentication**: Laravel Breeze
- **Server**: Nginx + PHP-FPM (production)
- **Security**: HTTPS via Let's Encrypt SSL certificates

## Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & NPM
- SQLite

### Quick Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/johnhringiv/gotflashes.git
   cd gotflashes
   ```

2. **Install dependencies and setup**
   ```bash
   composer setup
   ```
   This command will:
   - Install PHP dependencies
   - Copy `.env.example` to `.env`
   - Generate application key
   - Run database migrations
   - Install Node dependencies
   - Build frontend assets

3. **Start development server**
   ```bash
   composer dev
   ```
   This runs multiple services concurrently:
   - Laravel development server (http://localhost:8000)
   - Queue worker
   - Log viewer (Pail)
   - Vite dev server for hot module reload

4. **Access the application**
   Open http://localhost:8000 in your browser

### Manual Setup (Alternative)

If you prefer step-by-step setup:

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Install Node dependencies
npm install

# Build assets
npm run build

# Start development server
php artisan serve
```

### Creating Your First Admin User

After setup, register a new account through the web interface. To grant admin privileges, use Laravel Tinker:

```bash
php artisan tinker
```

Then run:
```php
$user = User::where('email', 'your@email.com')->first();
$user->is_admin = true;
$user->save();
```

## Development Workflow

### Running Tests
```bash
composer test
```

### Code Quality & Linting

This project uses multiple linters to maintain code quality. Two simple commands handle everything:

```bash
# Check all code (PHP, JavaScript, CSS)
composer check

# Auto-fix all fixable issues
composer fix
```

#### What Gets Checked

**`composer check`** runs:
- **Laravel Pint** - PHP code formatting (PSR-12)
- **PHPStan** - PHP static analysis (type safety, bug detection)
- **ESLint** - JavaScript linting
- **Stylelint** - CSS linting

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

If any check fails, the commit will be blocked until you run `composer fix` and fix any remaining issues.

### Database Management

**Create a new migration:**
```bash
php artisan make:migration create_table_name
```

**Run migrations:**
```bash
php artisan migrate
```

**Rollback last migration:**
```bash
php artisan migrate:rollback
```

**Fresh database (WARNING: destroys all data):**
```bash
php artisan migrate:fresh
```

### Useful Artisan Commands

```bash
# Clear all caches
php artisan optimize:clear

# View routes
php artisan route:list

# Open Tinker REPL
php artisan tinker

# View real-time logs
php artisan pail
```

## Project Structure

```
gotflashes/
├── app/
│   ├── Http/Controllers/    # Request handling logic
│   ├── Models/              # Eloquent models (User, Flash)
│   └── ...
├── database/
│   ├── migrations/          # Database schema definitions
│   └── database.sqlite      # SQLite database file
├── docs/
│   ├── prd.md              # Product Requirements Document
│   └── CONTRIBUTING.md     # Contribution guidelines
├── resources/
│   ├── views/              # Blade templates
│   ├── css/                # Stylesheets
│   └── js/                 # JavaScript files
├── routes/
│   └── web.php            # Web routes
├── tests/                 # PHPUnit tests
└── public/               # Web server document root
```

## Documentation

- **[Product Requirements](docs/prd.md)**: Detailed feature specifications and business rules
- **[Contributing](docs/CONTRIBUTING.md)**: Guidelines for contributing to the project

## Configuration

Key environment variables in `.env`:

```env
APP_NAME="GOT-FLASHES Challenge"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

For production deployment configuration, see the `.env` file and Laravel deployment documentation.

## Current Implementation Status

**Completed Features:**
- ✅ User registration and authentication
- ✅ Profile management
- ✅ Activity logging (Flashes)
- ✅ Activity CRUD operations
- ✅ Basic dashboard

**In Progress:**
- 🔄 Award tier calculations
- 🔄 Progress tracking displays
- 🔄 Freebie day limits enforcement
- 🔄 Year-end rollover logic

**Planned:**
- 📋 Leaderboards (individual, fleet, district)
- 📋 Award administrator dashboard
- 📋 Historical year views
- 📋 CSV export functionality

## Git Workflow

Current branches:
- `tutorial`: Development branch for current implementation

See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for detailed git workflow and branching strategy.

## Testing

Run the test suite:
```bash
php artisan test
```

Run tests with coverage:
```bash
php artisan test --coverage
```

## Support & Contributing

This project is developed for the International Lightning Class Association. For questions about the GOT-FLASHES Challenge program, contact the ILCA office.

Developers interested in contributing should read [CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines on code style, testing, and pull request process.

## Acknowledgments

Built with Laravel, Tailwind CSS, and the Lightning Class sailing community in mind.

---

**GOT-FLASHES**: Get Out There - Let's keep Lightning sailing active and fun!
