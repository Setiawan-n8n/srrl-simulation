# Train Journey Simulation — Multi-Station Rail Yard

A Laravel + Filament web application that simulates train movements in and out of a station/rail yard based on daily schedule data, with a full admin panel for managing the underlying reference data (schedules, trains, tracks, signals, points, and connecting stations).

## Features

- **Admin panel (Filament)** for CRUD management of: Schedules, Trains, Tracks, Signals, Points, and connecting stations
- **Excel schedule import** — bulk import daily train schedules directly from the admin panel or via CLI command
- **Public simulation view** — animated SVG station layout showing trains moving according to schedule, with play/pause controls, speed adjustment, and a time slider
- **Automatic signal state** — signal indicators change color based on track occupancy derived from the active schedule
- **Multi-station architecture** — station selector tab supports multiple yards; two stations have precisely digitized layouts, others use simplified generic layouts

## Tech Stack

- PHP 8.1+ / Laravel 11
- Filament (admin panel)
- SQLite (lightweight, file-based database)
- PhpSpreadsheet (Excel import)
- Vanilla JS (SVG animation, no build step required)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

- `/` — public simulation view
- `/admin` — admin panel (set your own admin credentials via `.env` before deploying; the seeder creates a default account for local development only — change it immediately)

## Data Structure

| Table | Contents |
|---|---|
| `stations` | Station/connection codes, names, side (east/west) |
| `tracks` | Track/platform definitions |
| `signals` | Signal positions per track |
| `wesels` | Point/switch positions per track |
| `trains` | Train master data (number, name, category) |
| `train_schedules` | Daily schedule rows: arrival/departure time, track, connection |

## Notes on Data Accuracy

Track, signal, and point positions for the two precisely-modeled stations were digitized from station layout reference material and are indicative rather than a certified engineering source — they're intended for visualization purposes, not for operational or safety-critical use. Some station connection directions and switch topology are simplified or approximate and are flagged for review inside the admin panel itself.

Signals in this simulation reflect schedule-based track occupancy only — they do **not** model real interlocking logic or switch position safety logic.

## Development Notes

This application was built iteratively with AI-assisted development (Claude), including full syntax validation and unit testing of the core simulation logic (position/signal-state calculation). Some edge cases (e.g., schedule import column-mapping bugs, cross-station track code collisions) were identified and fixed during development — see commit history for details.

---
*This repository is shared as a portfolio/technical reference for the simulation engine, admin panel, and schedule-import architecture. Precise infrastructure layout data, source reference documents, and any client/project-identifying details have been intentionally omitted, as this reflects real infrastructure the author does not have rights to publish in detail.*
