<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\Service\TicketDashboardService;
use Drupal\ticketdesk\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the ticket desk dashboard.
 */
class TicketDashboardController extends ControllerBase {

  /**
   * Date format used on the dashboard (m/D/Y).
   */
  private const DATE_FORMAT = 'n/j/Y';

  public function __construct(
    protected readonly TicketDashboardService $dashboardService,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(TicketDashboardService::class),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays a sortable ticket table for the dashboard.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function view(): array {
    $header = $this->buildHeader();
    $tickets = $this->loadTickets($header);
    $rows = array_map(fn (TicketInterface $ticket): array => $this->buildRow($ticket), $tickets);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ticketdesk-dashboard'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No tickets found.'),
        '#attributes' => [
          'class' => ['ticketdesk-dashboard__table'],
        ],
      ],
      '#attached' => [
        'library' => ['ticketdesk/dashboard'],
      ],
      '#cache' => [
        'tags' => $this->dashboardService->getCacheTags(),
        'contexts' => [
          'user',
          'url.query_args:sort',
          'url.query_args:order',
        ],
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

  /**
   * Builds the sortable table header.
   *
   * @return array<string, array<string, mixed>|string>
   */
  protected function buildHeader(): array {
    return [
      'id' => [
        'data' => $this->t('Ticket ID'),
        'field' => 'id',
        'specifier' => 'id',
        'sort' => 'desc',
      ],
      'title' => [
        'data' => $this->t('Title'),
        'field' => 'title',
        'specifier' => 'title',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
      ],
      'priority' => [
        'data' => $this->t('Priority'),
        'field' => 'priority',
        'specifier' => 'priority',
      ],
      'assignee' => [
        'data' => $this->t('Assignee'),
      ],
      'created' => [
        'data' => $this->t('Created'),
        'field' => 'created',
        'specifier' => 'created',
      ],
      'changed' => [
        'data' => $this->t('Last Updated'),
        'field' => 'changed',
        'specifier' => 'changed',
      ],
    ];
  }

  /**
   * Loads tickets visible to the current user.
   *
   * @param array<string, array<string, mixed>|string> $header
   *   Table header definition, passed by reference for sorting.
   *
   * @return \Drupal\ticketdesk\TicketInterface[]
   */
  protected function loadTickets(array &$header): array {
    $account = $this->currentUser();
    $query = $this->entityTypeManager()->getStorage('ticket')->getQuery()
      ->accessCheck(TRUE);

    $involvement = $query->orConditionGroup()
      ->condition('uid', $account->id())
      ->condition('assignee', $account->id());
    $query->condition($involvement);

    $query->tableSort($header);

    $ids = $query->execute();
    if ($ids === []) {
      return [];
    }

    return $this->entityTypeManager()->getStorage('ticket')->loadMultiple($ids);
  }

  /**
   * Builds a table row for a ticket.
   *
   * @return array<string, mixed>
   */
  protected function buildRow(TicketInterface $ticket): array {
    $assignee = $ticket->get('assignee')->entity;

    return [
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
      'assignee' => $assignee ? $assignee->getDisplayName() : $this->t('Unassigned'),
      'created' => [
        'data' => $this->formatDate((int) $ticket->get('created')->value),
        'class' => ['ticketdesk-dashboard__date'],
      ],
      'changed' => [
        'data' => $this->formatDate($ticket->getChangedTime()),
        'class' => ['ticketdesk-dashboard__date'],
      ],
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
