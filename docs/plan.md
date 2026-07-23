Phase 1: Scaffold module, Ticket content entity, handlers, permissions/roles, install hooks — done
Phase 2: TicketTransitionService, access rules, TicketDashboardService, presave subscriber — done
Phase 3: Add/edit/delete forms, view builder, formatters, concurrency hidden field — done
Phase 4: JSON API routes/controllers, 422/404/403 error contract, request validation — done
Phase 5: Dashboard controller, Twig template, cache tags, menu links — done
Phase 6: Unit, kernel, and functional tests for transitions, access, API, dashboard, concurrency — done
Phase 7: docs/plan.md, docs/api.md, README updates, scripts/seed-tickets.php — done

## Post-plan UX changes

- Dashboard is the site front page (`/`) for authenticated users.
- Requesters can access the dashboard to see tickets they raised or are assigned to.
- Dashboard table includes a **Take Action** column with an edit link when permitted.
- The default **Home** menu link is hidden from the main navigation.
