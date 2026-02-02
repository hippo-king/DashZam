# DashZam — Application Overview

## Purpose

DashZam is a Laravel-based live rink schedule display. It fetches remote event data, highlights what is happening now, and presents a high-contrast, large-format view suitable for distance readability.

## What it does

- Displays a 24‑hour rink schedule with “Live” callouts and progress bars.
- Applies rules for special events like ice resurfacing (“Takedown”) and “Close Rink”.
- Parses locker room tokens from event titles (e.g., "(BR, CH, 1-8)") for quick visibility.
- Supports a mock data mode for safe testing without calling the remote API.

## Primary pages & routes

- Public live view: /events (shows live + upcoming events).
- Admin API configuration: /api-settings (auth required) to set credentials and fetch data.
- Authenticated dashboard and profile management (Laravel Breeze defaults).

## Data flow (high level)

1. An authenticated user sets API credentials in the API Settings page.
2. “Fetch Now” calls the remote API, stores the response payload on the user.
3. The public /events page reads the most recent payload and renders live and upcoming events.
4. If EVENTS_USE_MOCK=true, mock events are shown instead of remote data.

## Business rules implemented

- “Takedown” events are treated as ice resurfacing.
- The final “Takedown” per rink is marked as “Close Rink”.
- “Spokane Braves vs …” with BR and CH locker rooms triggers a resurfacing reminder.

## Configuration

- EVENTS_USE_MOCK=true|false (see .env) to switch between mock and live data.
- API credentials and last payload are stored per user (encrypted where appropriate).

## Tech stack

- Backend: Laravel 12 (PHP 8.2)
- Frontend: Blade templates, Tailwind CSS, Alpine.js, Vite
- Auth scaffolding: Laravel Breeze

## Key files

- routes/web.php — Route definitions
- app/Http/Controllers/PublicEventsController.php — Live schedule assembly
- app/Http/Controllers/ApiSettingsController.php — API auth + fetch
- app/Support/LockerRoomParser.php — Locker room parsing utility
- resources/views/public/events.blade.php — Live schedule UI
- resources/views/api-settings/edit.blade.php — API settings UI
