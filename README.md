# YSLEP Service Ledger

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)](https://vitejs.dev)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?logo=postgresql&logoColor=white)](https://postgresql.org)
[![Obsidian](https://img.shields.io/badge/Obsidian-synced-7C3AED?logo=obsidian&logoColor=white)](https://obsidian.md)

A Laravel-based service hour tracking and reporting application that syncs with an Obsidian vault. Manage service records across three index types (Formation, Social Apostolate, Parish Involvement) with live inline editing, saved report snapshots, and academic-year archiving.

```mermaid
flowchart LR
    OV[("Obsidian Vault\n.md files")]
    LA["Laravel App"]
    PG[("PostgreSQL")]

    OV <-->|YAML front-matter| LA
    LA <-->|records| PG
```

## Documentation

| Section | File |
|---|---|
| Features and screenshots | [docs/overview.md](docs/overview.md) |
| Architecture, models, and data flow | [docs/architecture.md](docs/architecture.md) |
| Installation and configuration | [docs/installation.md](docs/installation.md) |
| Usage guide and routes | [docs/usage.md](docs/usage.md) |
| Testing, code style, and contributing | [docs/development.md](docs/development.md) |

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
composer run setup
composer run dev
```

See [docs/installation.md](docs/installation.md) for detailed setup instructions.

## License

The YSLEP Service Ledger is open-sourced software licensed under the [MIT license](LICENSE).
