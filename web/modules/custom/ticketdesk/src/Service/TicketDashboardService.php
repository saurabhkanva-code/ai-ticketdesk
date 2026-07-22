<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ticketdesk\Entity\Ticket;

/**
 * Provides aggregated ticket counts for the dashboard.
 */
class TicketDashboardService {

  /**
   * Cache tag for ticket list aggregates.
   */
  public const LIST_CACHE_TAG = 'ticketdesk:ticket_list';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns ticket counts keyed by status machine name.
   *
   * @return array<string, int>
   */
  public function getCountsByStatus(): array {
    $counts = $this->zeroFill(Ticket::getStatusOptions());
    $results = $this->entityTypeManager->getStorage('ticket')->getAggregateQuery()
      ->accessCheck(TRUE)
      ->aggregate('id', 'COUNT')
      ->groupBy('status')
      ->execute();

    foreach ($results as $result) {
      $status = $result['status'];
      if (array_key_exists($status, $counts)) {
        $counts[$status] = (int) $result['id_count'];
      }
    }

    return $counts;
  }

  /**
   * Returns ticket counts keyed by priority machine name.
   *
   * @return array<string, int>
   */
  public function getCountsByPriority(): array {
    $counts = $this->zeroFill(Ticket::getPriorityOptions());
    $results = $this->entityTypeManager->getStorage('ticket')->getAggregateQuery()
      ->accessCheck(TRUE)
      ->aggregate('id', 'COUNT')
      ->groupBy('priority')
      ->execute();

    foreach ($results as $result) {
      $priority = $result['priority'];
      if (array_key_exists($priority, $counts)) {
        $counts[$priority] = (int) $result['id_count'];
      }
    }

    return $counts;
  }

  /**
   * Returns cache tags used by dashboard output.
   *
   * @return list<string>
   */
  public function getCacheTags(): array {
    return [self::LIST_CACHE_TAG];
  }

  /**
   * Builds a zero-filled count array for the given option labels.
   *
   * @param array<string, string> $options
   *
   * @return array<string, int>
   */
  private function zeroFill(array $options): array {
    return array_fill_keys(array_keys($options), 0);
  }

}
