<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to sync dashboard filter values into the browser URL.
 */
final class TicketdeskUpdateUrlCommand implements CommandInterface {

  /**
   * @param array<string, string> $filters
   */
  public function __construct(
    protected readonly array $filters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'ticketdeskDashboardUpdateUrl',
      'filters' => $this->filters,
    ];
  }

}
