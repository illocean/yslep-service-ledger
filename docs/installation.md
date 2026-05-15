# Installation

## Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 20+ and npm
- PostgreSQL 15+
- An Obsidian vault (optional, required for sync features)

---

## Setup

```bash
# Clone the repository
git clone https://github.com/illocean/yslep-service-ledger.git
cd yslep-service-ledger

# Install PHP dependencies
composer install

# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Install frontend dependencies
npm install

# Build frontend assets
npm run build

# Run the setup command (migrate + build)
composer run setup
```

---

## Environment Configuration

Configure the following variables in your `.env` file:

```env
APP_NAME="YSLEP Service Ledger"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=yslep_ledger
DB_USERNAME=postgres
DB_PASSWORD=secret

# Obsidian vault path (where your Markdown index notes live)
OBSIDIAN_VAULT_PATH=/path/to/your/vault
REPORT_GROUPS_FILE_PATH=/path/to/report-groups.yml
```

---

## Development Server

```bash
# Start all services (server, queue, logs, Vite)
composer run dev
```

This runs four processes concurrently:

- `php artisan serve` -- Laravel development server
- `php artisan queue:listen` -- Queue worker for background jobs
- `php artisan pail` -- Log tailing
- `npm run dev` -- Vite HMR for frontend assets
