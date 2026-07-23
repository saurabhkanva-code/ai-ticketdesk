<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\Form\TicketDashboardFiltersForm;
use Drupal\ticketdesk\Service\TicketAccessService;
use Drupal\ticketdesk\TicketInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the filtered ticket table for the dashboard.
 */
class TicketDashboardResultsBuilder {

  /**
   * Date format used on the dashboard (m/D/Y).
   */
  private const DATE_FORMAT = 'n/j/Y';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly RequestStack $requestStack,
    protected readonly TicketAccessService $ticketAccess,
  ) {}

  /**
   * Whether the current user can view all tickets on the dashboard.
   */
  public function canViewAllTickets(): bool {
    return $this->ticketAccess->canViewAll($this->currentUser->getAccount());
  }

  /**
   * Reads active dashboard filter values from the request or an override.
   *
   * @param array<string, string>|null $override
   *   Optional filter values (e.g. from an AJAX form submit).
   *
   * @return array{
   *   requester: string,
   *   assignee: string,
   *   status: string,
   *   priority: string,
   * }
   */
  public function getActiveFilters(?array $override = NULL): array {
    if ($override !== NULL) {
      return $this->normalizeFilters($override);
    }

    $request = $this->requestStack->getCurrentRequest();
    return $this->normalizeFilters([
      'requester' => (string) ($request?->query->get('requester') ?? ''),
      'assignee' => (string) ($request?->query->get('assignee') ?? ''),
      'status' => (string) ($request?->query->get('status') ?? ''),
      'priority' => (string) ($request?->query->get('priority') ?? ''),
    ]);
  }

  /**
   * Builds the dashboard results wrapper and ticket table.
   *
   * @param array<string, string>|null $filters
   *   Optional filter override.
   *
   * @return array<string, mixed>
   */
  public function buildResults(?array $filters = NULL): array {
    $view_all = $this->canViewAllTickets();
    $active_filters = $this->getActiveFilters($filters);
    $header = $this->buildHeader($view_all);
    $tickets = $this->loadTickets($header, $view_all, $active_filters);
    $rows = array_map(
      fn (TicketInterface $ticket): array => $this->buildRow($ticket, $view_all),
      $tickets,
    );

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ticketdesk-dashboard-results',
        'class' => ['ticketdesk-dashboard__results'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('No tickets found.'),
        '#attributes' => [
          'class' => ['ticketdesk-dashboard__table'],
        ],
      ],
    ];
  }

  /**
   * Normalizes filter values.
   *
   * @param array<string, string> $filters
   *
   * @return array{
   *   requester: string,
   *   assignee: string,
   *   status: string,
   *   priority: string,
   * }
   */
  public function normalizeFilters(array $filters): array {
    return [
      'requester' => (string) ($filters['requester'] ?? ''),
      'assignee' => (string) ($filters['assignee'] ?? ''),
      'status' => (string) ($filters['status'] ?? ''),
      'priority' => (string) ($filters['priority'] ?? ''),
    ];
  }

  /**
   * Builds the sortable table header.
   *
   * @return array<string, array<string, mixed>|string>
   */
  protected function buildHeader(bool $view_all): array {
    $header = [
      'id' => [
        'data' => t('Ticket ID'),
        'field' => 'id',
        'specifier' => 'id',
        'sort' => 'desc',
      ],
      'title' => [
        'data' => t('Title'),
        'field' => 'title',
        'specifier' => 'title',
      ],
      'status' => [
        'data' => t('Status'),
        'field' => 'status',
        'specifier' => 'status',
      ],
      'priority' => [
        'data' => t('Priority'),
        'field' => 'priority',
        'specifier' => 'priority',
      ],
    ];

    if ($view_all) {
      $header['requester'] = [
        'data' => t('Requester'),
      ];
    }

    $header['assignee'] = [
      'data' => t('Assignee'),
    ];
    $header['created'] = [
      'data' => t('Created'),
      'field' => 'created',
      'specifier' => 'created',
    ];
    $header['changed'] = [
      'data' => t('Last Updated'),
      'field' => 'changed',
      'specifier' => 'changed',
    ];
    $header['action'] = [
      'data' => t('Take Action'),
    ];

    return $header;
  }

  /**
   * Loads tickets visible to the current user.
   *
   * @param array<string, array<string, mixed>|string> $header
   * @param array{
   *   requester: string,
   *   assignee: string,
   *   status: string,
   *   priority: string,
   * } $filters
   *
   * @return \Drupal\ticketdesk\TicketInterface[]
   */
  protected function loadTickets(array &$header, bool $view_all, array $filters): array {
    $account = $this->currentUser->getAccount();
    $query = $this->entityTypeManager->getStorage('ticket')->getQuery()
      ->accessCheck(TRUE);

    if (!$view_all) {
      $this->ticketAccess->applyInvolvementConditions($query, $account);
    }
    else {
      $this->applyFilters($query, $filters);
    }

    $query->tableSort($header);

    $ids = $query->execute();
    if ($ids === []) {
      return [];
    }

    return $this->entityTypeManager->getStorage('ticket')->loadMultiple($ids);
  }

  /**
   * Applies exposed dashboard filters to a ticket query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   * @param array{
   *   requester: string,
   *   assignee: string,
   *   status: string,
   *   priority: string,
   * } $filters
   */
  protected function applyFilters($query, array $filters): void {
    if ($filters['requester'] !== '' && ctype_digit($filters['requester'])) {
      $query->condition('uid', (int) $filters['requester']);
    }

    if ($filters['assignee'] === TicketDashboardFiltersForm::ASSIGNEE_UNASSIGNED) {
      $query->condition('assignee', NULL, 'IS NULL');
    }
    elseif ($filters['assignee'] !== '' && ctype_digit($filters['assignee'])) {
      $query->condition('assignee', (int) $filters['assignee']);
    }

    if ($filters['status'] !== '' && array_key_exists($filters['status'], Ticket::getStatusOptions())) {
      $query->condition('status', $filters['status']);
    }

    if ($filters['priority'] !== '' && array_key_exists($filters['priority'], Ticket::getPriorityOptions())) {
      $query->condition('priority', $filters['priority']);
    }
  }

  /**
   * Builds a table row for a ticket.
   *
   * @return array<string, mixed>
   */
  protected function buildRow(TicketInterface $ticket, bool $view_all): array {
    $assignee = $ticket->get('assignee')->entity;
    $owner = $ticket->getOwner();

    $row = [
      'id' => $ticket->id(),
      'title' => $ticket->toLink($ticket->getTitle()),
      'status' => [
        'data' => $this->buildBadge(
          'status',
          $ticket->getStatus(),
          Ticket::getStatusOptions()[$ticket->getStatus()] ?? $ticket->getStatus(),
        ),
      ],
      'priority' => [
        'data' => $this->buildBadge(
          'priority',
          $ticket->getPriority(),
          Ticket::getPriorityOptions()[$ticket->getPriority()] ?? $ticket->getPriority(),
        ),
      ],
    ];

    if ($view_all) {
      $row['requester'] = $owner ? $owner->getDisplayName() : t('Unknown');
    }

    $row['assignee'] = $assignee ? $assignee->getDisplayName() : t('Unassigned');
    $row['created'] = [
      'data' => $this->formatDate((int) $ticket->get('created')->value),
      'class' => ['ticketdesk-dashboard__date'],
    ];
    $row['changed'] = [
      'data' => $this->formatDate($ticket->getChangedTime()),
      'class' => ['ticketdesk-dashboard__date'],
    ];
    $row['action'] = $this->buildActionLink($ticket);

    return $row;
  }

  /**
   * Builds the Take Action column for a ticket row.
   *
   * @return array<string, mixed>|string
   */
  protected function buildActionLink(TicketInterface $ticket): array|string {
    if (!$ticket->access('update')) {
      return [
        'data' => t('—'),
        'class' => ['ticketdesk-dashboard__no-action'],
      ];
    }

    return [
      'data' => $ticket->toLink(t('Edit ticket'), 'edit-form', [
        'attributes' => ['class' => ['ticketdesk-dashboard__action-link']],
      ]),
      'class' => ['ticketdesk-dashboard__action'],
    ];
  }

  /**
   * Formats a timestamp for dashboard display.
   */
  protected function formatDate(int $timestamp): string {
    return $this->dateFormatter->format($timestamp, 'custom', self::DATE_FORMAT);
  }

  /**
   * Builds a status or priority badge render element.
   *
   * @return array<string, mixed>
   */
  protected function buildBadge(string $type, string $key, string $label): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<span class="ticketdesk-badge ticketdesk-badge--{{ type }}-{{ key|clean_class }}">{{ label }}</span>',
      '#context' => [
        'type' => $type,
        'key' => $key,
        'label' => $label,
      ],
    ];
  }

}
