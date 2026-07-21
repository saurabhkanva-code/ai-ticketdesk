# Requirements — AI Ticket Desk

## Problem statement
Internal teams need a way to raise, triage, and resolve support tickets,
with visibility into ticket volume and status for managers. Requesters
raise tickets; agents pick them up and move them through a fixed
lifecycle; anyone with dashboard access sees counts by status/priority.

## Most ambiguous part of the requirement
No SLA, notification, or attachment behavior was specified. I'm treating
the core lifecycle + dashboard as in-scope and everything else as a
documented assumption/future improvement (see Limitations in README).

## Assumptions
- Single tenant, no multi-org support.
- Roles: requester, agent, admin (mapped to Drupal roles/permissions).
- No email notifications in v1.
- Ticket priority is set at creation and editable by agents only.
- One assignee per ticket; unassigned is a valid state.
- Reopen is allowed only from `resolved`, not from `closed`.

## Questions I'd ask a product owner
- Is there an SLA/due-date requirement per priority?
- Can a requester close their own ticket, or only agents?
- Is there a reopen time window (e.g. 7 days) after closure?
- Do tickets need file attachments?
- Should closed tickets be edit-locked entirely, or just status-locked?

## Acceptance criteria
- AC1: A requester can create a ticket with title, description, priority.
- AC2: Status transitions only follow: open -> in_progress -> resolved
  -> closed, with resolved -> open (reopen) also allowed.
- AC3: An invalid transition attempt returns a 422 with a clear message,
  both via API and via the edit form.
- AC4: The dashboard shows a count of tickets per status and per
  priority, updating as tickets change.
- AC5: Only authenticated users with appropriate permission can
  view/act on tickets; anonymous access is denied.

## Edge cases identified
- Two agents updating the same ticket's status concurrently.
- Transition attempted on a nonexistent or deleted ticket.
- Dashboard rendered with zero tickets in the system.
- Ticket with an assignee whose user account has since been deleted.
- Script/HTML submitted in the description field (XSS).
- Transition attempted directly from `open` to `closed` (skipping steps).
