# AI Ticket Desk

A support ticket management system built on Drupal 11, developed with
Cursor as an AI-assisted development capability exercise.

## Problem this solves

Requesters raise support tickets; agents triage and move them through a
fixed status lifecycle; a dashboard shows tickets the current user is
involved in. See `docs/requirements.md` for full requirements, assumptions,
and acceptance criteria.

## Tech stack

- Drupal 11, PHP 8.3
- DDEV (local environment) + MariaDB
- Custom module: `web/modules/custom/ticketdesk`

## Prerequisites

- Docker (running)
- DDEV (`ddev version` to confirm)
- Git

## Setup — from a clean clone

```bash
git clone git@github.com:saurabhkanva-code/ai-ticketdesk.git
cd ai-ticketdesk
ddev start
ddev composer install
ddev drush site:install standard -y
ddev drush en ticketdesk -y
ddev drush php:script scripts/seed-tickets.php
```

Site is then available at the URL shown by `ddev describe` (or `ddev launch`).

The ticket dashboard is configured as the site front page (`/`). Anonymous
visitors are redirected to log in; authenticated users land on the dashboard.

## Roles

| Role | Capabilities |
|------|----------------|
| `ticketdesk_requester` | Create tickets; view/edit own open tickets; view tickets assigned to them |
| `ticketdesk_agent` | View/edit tickets they created or are assigned to; transition status; assign |
| `ticketdesk_admin` | View/edit/delete all tickets; full dashboard filters |

## Database setup & seed data

`ddev drush site:install` creates the database and schema. To load sample
users and tickets:

```bash
ddev drush php:script scripts/seed-tickets.php
```

Seed accounts use password `ticketdesk` when first created:

- `requester1` — requester role
- `agent1` — agent role
- `admin1` — admin role

Re-running the script is safe; it skips tickets with duplicate titles.

## Running tests

```bash
ddev exec bash scripts/run-ticketdesk-tests.sh
```

Or manually:

```bash
ddev exec bash -c 'cd web && SIMPLETEST_DB=mysql://db:db@db/db SIMPLETEST_BASE_URL=http://127.0.0.1 ../vendor/bin/phpunit -c core modules/custom/ticketdesk/tests'
```

## AI-assisted development workflow

This project was built with Cursor. Persistent project context and
coding standards are defined in `.cursor/rules/drupal.mdc`. Prompt
history, including corrections and dead-ends, is logged incrementally
in `ai-prompts/`, committed alongside the code each session produced.

## Project docs

- `docs/requirements.md` — problem statement, assumptions, acceptance criteria, edge cases
- `docs/plan.md` — implementation plan and post-plan UX notes
- `docs/api.md` — JSON API contract

## Limitations / not yet implemented

- No email or in-app notifications
- No file attachments on tickets
- No SLA or due dates per priority
- Dashboard shows a ticket table (not aggregate count widgets); counts are available via `TicketDashboardService` for future UI
- Delete is restricted to ticket administrators
- Single-tenant only; no multi-org support
