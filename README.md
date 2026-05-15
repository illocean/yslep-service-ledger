# YSLEP Service Ledger

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)](https://vitejs.dev)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Build](https://img.shields.io/badge/build-passing-success)](https://github.com/pandadoor/yslep-service-ledger/actions)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?logo=postgresql&logoColor=white)](https://postgresql.org)
[![Obsidian](https://img.shields.io/badge/Obsidian-synced-7C3AED?logo=obsidian&logoColor=white)](https://obsidian.md)

A Laravel-based service hour tracking and reporting application that syncs with an Obsidian vault. The YSLEP Service Ledger lets you manage service records across three index types -- Formation, Social Apostolate, and Parish Involvement -- with live, inline editing, saved report snapshots, and academic-year archiving.

---

## Features

### Live Record Management
- **Three index types** -- Manage Formation, Social Apostolate, and Parish Involvement records independently
- **Inline editing** -- Expand any record to edit its fields in place without leaving the page
- **Scope filtering** -- Switch between All Time, Unsaved, and Saved Report scopes with one click
- **Real-time add/update/delete** -- Changes are persisted immediately to PostgreSQL

### Obsidian Integration
- **Two-way sync** -- Records are sourced from and written to plain Markdown files in an Obsidian vault
- **YAML front-matter parsing** -- Reads structured data from Obsidian notes via `spatie/yaml-front-matter`
- **Live source editing** -- Edit the index note directly, or add records through the web UI and have them appended to the matching Markdown file
- **Sync from vault** -- Pull the latest edits made in Obsidian into PostgreSQL

### Saved Reports
- **Snapshot reports** -- Freeze a set of live records into a named, tagged saved report
- **Per-report records** -- Add, edit, or remove records within the scope of a saved report
- **Obsidian note mirroring** -- Each saved report generates and tracks its own summary note and per-record notes
- **Locking** -- Entries captured in a saved report are locked and tagged in the live view

### Academic Year Archiving
- **Snapshot builder** -- Create academic-year archives from the live service ledger
- **Dedicated archive view** -- Browse and manage archived academic years separately from active records

### User Interface
- **Paper-ledger aesthetic** -- Warm, cream-toned design with serif headings and a physical ledger feel
- **Progressive disclosure** -- Forms and record details are hidden behind accordion toggles, reducing cognitive load
- **Keyboard accessible** -- Full focus-visible outlines, skip-to-content link, and screen-reader friendly markup
- **Responsive** -- Adapts from mobile to wide desktop layouts
- **Staggered page animations** -- Subtle fade-in transitions for a polished feel

---

## Architecture

```
                   +------------------+
                   |  Obsidian Vault  |
                   |  (.md files)     |
                   +--------+---------+
                            |
                    reads/writes YAML
                            |
                   +--------+---------+
                   |  Laravel App     |
                   |                  |
                   |  +------------+  |
                   |  | Controllers|  |
                   |  +-----+------+  |
                   |        |         |
                   |  +-----v------+  |
                   |  |  Services  |  |
                   |  +-----+------+  |
                   |        |         |
                   |  +-----v------+  |
                   |  |  Models    |  |
                   |  +-----+------+  |
                   |        |         |
                   +--------+---------+
                            |
                   +--------+---------+
                   |   PostgreSQL     |
                   +------------------+
```

### Data Flow

1. **Live entries** are stored in PostgreSQL and backed by Markdown notes in an Obsidian vault
2. The web UI reads from PostgreSQL and writes back to both the database and the Markdown source files
3. **Saved reports** group a subset of live entries under a unique tag, with their own set of record notes
4. **Academic-year snapshots** capture a point-in-time view of all entries for archival purposes

### Models

| Model | Purpose |
|---|---|
| `FormationEntry` | Formation service records (cycle, module, title, time) |
| `SocialApostolateEntry` | Social apostolate records (activity, time) |
| `ParishInvolvementEntry` | Parish involvement records (time) |
| `ReportGroup` | A named, tagged saved report snapshot |
| `ReportGroupItem` | A record attached to a saved report |
| `AcademicYearSnapshot` | An archived academic year snapshot |
| `AcademicYearSnapshotItem` | A record attached to a snapshot |

### Enums

| Enum | Values |
|---|---|
| `IndexType` | `formation`, `parish_involvement`, `social_apostolate` |
| `IndexScope` | `all`, `unsaved`, `report` |

---

## Screenshots

> Screenshots are pending. Below is a summary of the key views:

- **Dashboard** -- Overview with per-type stat cards, grand total, and saved report sidebar
- **Index Show** -- Per-type record management with scope switcher, inline editing, and add form
- **Saved Reports** -- List of saved report snapshots with per-type record breakdowns
- **Report Detail** -- Individual saved report management with rename, sync, delete, and per-type record sections
- **Academic Year Snapshots** -- Archive browsing and snapshot builder

---

## Installation

### Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 20+ and npm
- PostgreSQL 15+
- An Obsidian vault (optional, required for sync features)

### Setup

```bash
# Clone the repository
git clone https://github.com/pandadoor/yslep-service-ledger.git
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

### Environment Configuration

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

### Development Server

```bash
# Start all services (server, queue, logs, Vite)
composer run dev
```

This runs four processes concurrently:
- `php artisan serve` -- Laravel development server
- `php artisan queue:listen` -- Queue worker for background jobs
- `php artisan pail` -- Log tailing
- `npm run dev` -- Vite HMR for frontend assets

---

## Usage

### Managing Live Records

1. Navigate to an index page (Formation, Social Apostolate, or Parish Involvement)
2. Use the scope switcher to filter records (All Time / Unsaved / Saved Report)
3. Click the "Add Record" accordion to open the creation form
4. Fill in the fields and submit
5. Existing records can be expanded inline -- click the row to reveal edit and delete controls

### Creating Saved Reports

1. From the Dashboard, scroll to the "Save Report Group" section
2. Expand the accordion
3. Select a title (optional) and pick entries from the available categories
4. Click "Save Report" to create the snapshot
5. Manage saved reports from the Reports page

### Syncing with Obsidian

1. Edit your Markdown source notes directly in Obsidian
2. Navigate to Reports and click "Sync from Obsidian"
3. The application reads the YAML front-matter and updates PostgreSQL accordingly

---

## Routes

| Method | URI | Action |
|---|---|---|
| `GET` | `/` | Dashboard overview |
| `GET` | `/indexes/{type}` | Per-type record management |
| `POST` | `/entries` | Create a live entry |
| `PATCH` | `/entries/{entry}` | Update a live entry |
| `DELETE` | `/entries/{entry}` | Delete a live entry |
| `POST` | `/report-groups` | Create a saved report |
| `GET` | `/reports` | Saved reports list |
| `GET` | `/reports/{reportGroup}` | Saved report detail |
| `PATCH` | `/reports/{reportGroup}` | Update a saved report |
| `DELETE` | `/reports/{reportGroup}` | Delete a saved report |
| `POST` | `/reports/{reportGroup}/records` | Add record to report |
| `PATCH` | `/reports/{reportGroup}/records/{item}` | Update report record |
| `DELETE` | `/reports/{reportGroup}/records/{item}` | Delete report record |
| `POST` | `/reports/sync-from-obsidian` | Sync notes from vault |
| `GET` | `/academic-year-snapshots` | Academic year archives |
| `POST` | `/academic-year-snapshots` | Create a snapshot |
| `DELETE` | `/academic-year-snapshots/{snapshot}` | Delete a snapshot |

---

## Testing

```bash
# Run the full test suite
composer run test

# Or directly
php artisan test
```

---

## Development

### Code Style

This project follows Laravel Pint (PSR-12) coding standards:

```bash
./vendor/bin/pint --test
```

### Frontend Build

```bash
# Production build
npm run build

# Development with HMR
npm run dev
```

The frontend uses:
- **Tailwind CSS v4** -- Utility-first CSS framework
- **Vite** -- Build tool with HMR
- **Alpine.js / vanilla JS** -- Progressive enhancement (minimal JavaScript)

---

## Contributing

Contributions are welcome. Please submit a pull request or open an issue to discuss proposed changes.

---

## License

The YSLEP Service Ledger is open-sourced software licensed under the [MIT license](LICENSE).
