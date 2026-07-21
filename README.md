# AI Ticket Desk


A support ticket management system built on Drupal 11, developed with
Cursor as an AI-assisted development capability exercise.

## Problem this solves

Requesters raise support tickets; agents triage and move them through a
fixed status lifecycle; a dashboard shows live counts by status and
priority. See `docs/requirements.md` for full requirements, assumptions,
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


\`\`\`bash
git clone git@github.com:saurabhkanva-code/ai-ticketdesk.git
cd ai-ticketdesk
ddev start
ddev composer install
ddev drush site:install standard -y
ddev drush en ticketdesk -y
\`\`\`

Site is then available at the URL shown by `ddev describe` (or `ddev launch`).

## Database setup & seed data

`ddev drush site:install` creates the database and schema. To load sample
tickets for testing/demo purposes:

\`\`\`bash
ddev drush php:script scripts/seed-tickets.php
\`\`\`

(Add this script when seed data is implemented — placeholder until then.)

## Running tests

\`\`\`bash
ddev exec phpunit -c web/core web/modules/custom/ticketdesk
\`\`\`

## AI-assisted development workflow

This project was built with Cursor. Persistent project context and
coding standards are defined in `.cursor/rules/drupal.mdc`. Prompt
history, including corrections and dead-ends, is logged incrementally
in `ai-prompts/`, committed alongside the code each session produced.

## Project docs

- `docs/requirements.md` — problem statement, assumptions, acceptance criteria, edge cases
- `docs/plan.md` — implementation plan (and how it diverged from AI's first draft)
- `docs/api.md` — API contract
- `docs/ai-review.md` — AI-assisted code review findings
- `docs/reflection.md` — development reflection, limitations, what's next

## Limitations / not yet implemented

(Update as you go — be specific and honest here; this is scored.)
