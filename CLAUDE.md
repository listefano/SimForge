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

## SimulationCraft Integration

SimForge wraps the SimulationCraft CLI (`simc`) to run DPS simulations.

### How SimC is invoked
- Input: `.simc` profile files (generated from character data)
- Execution: `simc input=profile.simc json2=result.json threads=8 iterations=10000`
- Output: JSON result with DPS metrics

### Droptimizer flow
1. User submits character profile (SimC addon export string)
2. Backend parses profile, creates base simulation
3. Item Service looks up loot table for selected content (dungeon/raid/crafting)
4. For each potential upgrade item: clone base profile, equip item, create Simulation record
5. All sims are dispatched as a SimulationBatch via Horizon
6. Worker runs simc CLI per simulation, parses JSON result
7. Results are compared against base DPS → ranked by DPS gain
8. Progress + results broadcast via Reverb WebSocket

### Profile format
SimC addon exports look like:
warrior="Listefano"
level=80
race=dwarf
spec=protection
# gear follows as item lines
