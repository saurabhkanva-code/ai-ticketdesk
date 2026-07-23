# Ticket Desk API

Custom JSON endpoints for ticket CRUD and lifecycle management. All routes
live under `/api/ticketdesk/tickets` and require an authenticated Drupal
session unless noted otherwise.

## Authentication

- Browser or API clients must be logged in (`_user_is_logged_in`).
- `POST` and `PATCH` require the `X-CSRF-Token` header. Obtain a token from
  `GET /session/token` while authenticated.
- Send `Content-Type: application/json` on write requests.

## Response format

### Success

```json
{
  "data": { ... }
}
```

List responses also include:

```json
{
  "data": [ ... ],
  "meta": {
    "count": 1
  }
}
```

### Error

```json
{
  "message": "Human-readable error message."
}
```

| HTTP status | When |
|-------------|------|
| 400 | Invalid JSON or validation error |
| 401 | Anonymous list request |
| 403 | Missing permission for the operation |
| 404 | Ticket not found |
| 409 | Stale `changed` timestamp (concurrency conflict) |
| 422 | Invalid status transition |

## Ticket object

| Field | Type | Notes |
|-------|------|-------|
| `id` | integer | Ticket ID |
| `uuid` | string | UUID |
| `title` | string | Required on create |
| `description` | string | Plain text; HTML stripped on input |
| `priority` | string | `low`, `medium`, `high`, `critical` |
| `status` | string | `open`, `in_progress`, `resolved`, `closed` |
| `assignee` | integer\|null | User ID of assigned agent |
| `requester` | integer\|null | Owner user ID |
| `created` | integer | Unix timestamp |
| `changed` | integer | Unix timestamp; required on PATCH |

## Endpoints

### `GET /api/ticketdesk/tickets`

Lists up to 100 tickets visible to the current user.

- Admins and users with `view any ticket` see all tickets.
- Other users see only tickets they created.

### `GET /api/ticketdesk/tickets/{id}`

Returns a single ticket. Requires `view` access on the ticket.

### `POST /api/ticketdesk/tickets`

Creates a ticket.

**Body**

```json
{
  "title": "VPN not connecting",
  "description": "Cannot reach internal apps remotely.",
  "priority": "high"
}
```

- `title` and `description` are required.
- `priority` defaults to `medium`.
- New tickets are created with status `open`.

**Response:** `201 Created` with the ticket object.

### `PATCH /api/ticketdesk/tickets/{id}`

Updates a ticket. Requires `update` access.

**Body** (all fields optional except `changed`)

```json
{
  "changed": 1721654400,
  "title": "Updated title",
  "description": "Updated description",
  "priority": "critical",
  "status": "in_progress",
  "assignee": 5
}
```

**Permission rules**

| Field | Who can change |
|-------|----------------|
| `title`, `description` | Ticket editor |
| `priority` | `edit any ticket` or `administer tickets` |
| `status` | `transition ticket` or `administer tickets` |
| `assignee` | `assign ticket` or `administer tickets` |

**Status transitions**

| From | Allowed next statuses |
|------|------------------------|
| `open` | `in_progress` |
| `in_progress` | `resolved` |
| `resolved` | `closed`, `open` (reopen) |
| `closed` | _(none)_ |

Invalid transitions return `422`.

## Example: create and transition

```bash
# Log in via browser or use an authenticated session cookie.
TOKEN=$(curl -s -b cookies.txt https://example.com/session/token)

curl -s -b cookies.txt -X POST https://example.com/api/ticketdesk/tickets \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"title":"Printer jam","description":"Paper stuck in tray 2","priority":"low"}'

curl -s -b cookies.txt -X PATCH https://example.com/api/ticketdesk/tickets/1 \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $TOKEN" \
  -d '{"changed":1721654400,"status":"in_progress"}'
```
