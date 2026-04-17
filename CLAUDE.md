# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (runs serve, horizon, reverb:start, pail concurrently)
composer dev

# Setup a fresh environment
composer setup  # installs deps, copies .env, generates key, runs migrations

# Tests
composer test           # clears config cache, then runs phpunit
php artisan test        # run all tests
php artisan test --filter TestClassName  # run a single test class
php artisan test tests/Feature/SomeTest.php  # run a specific file

# Code style
./vendor/bin/pint       # fix code style (Laravel Pint)

# Individual services
php artisan serve       # API server on :8000
php artisan horizon     # queue dashboard at /horizon
php artisan reverb:start  # WebSocket server on :8080
php artisan pail --timeout=0  # live log streaming
```

## Architecture

SimForge is a WoW loot & DPS simulation API. It uses **domain-driven design** — code is grouped by business domain, not technical layer.

### Domain structure (`app/Domain/`)

Each domain owns its full vertical slice:

```
app/Domain/
├── Character/          # Character management
│   ├── Models/         # Character.php
│   ├── Http/           # Controllers, Requests, Resources
│   ├── Actions/        # Business logic (single-purpose classes)
│   ├── DTOs/           # Data transfer objects
│   ├── Events/
│   ├── Jobs/
│   └── Repositories/
├── ItemDatabase/       # Item catalog and loot sources
│   ├── Models/         # Item.php, LootSource.php
│   ├── Http/
│   ├── Actions/
│   └── DTOs/
└── Simulation/         # DPS simulation engine
    ├── Models/         # Simulation.php, SimulationBatch.php
    ├── Http/
    ├── Actions/
    ├── DTOs/
    ├── Events/
    ├── Jobs/
    └── Services/
```

`app/Models/` holds only `User.php`. All domain models live under their respective `Domain/*/Models/`.

### Key relationships

- `SimulationBatch` hasMany `Simulation`
- `Simulation` belongsTo `Character` and `SimulationBatch`
- `Character` hasMany `Simulation`
- `stats`, `gear`, `config`, `results`, `loot_table`, `effects` columns are JSON, cast to arrays

### Infrastructure

| Concern | Driver |
|---|---|
| Queue | Redis + Horizon (`/horizon` dashboard) |
| Broadcasting | Reverb WebSocket (port 8080) |
| Cache | Redis |
| Sessions | Database |
| Database | MariaDB (SQLite in-memory for tests) |

### Routing

API routes live at `/api/*` (prefix configured in `bootstrap/app.php`). Domain-specific routes go in `routes/api/v1/{domain}.php` and are included from `routes/api.php`. There is a health check at `GET /api/up`.

### Testing

Tests use SQLite in-memory, synchronous queues, and array cache/session/broadcast — no external services needed. Add Feature tests under `tests/Feature/` and Unit tests under `tests/Unit/`.
