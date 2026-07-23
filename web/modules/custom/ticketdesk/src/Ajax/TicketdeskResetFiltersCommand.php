<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to clear dashboard filter controls in the browser.
 */
final class TicketdeskResetFiltersCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'ticketdeskDashboardResetFilters',
    ];
  }

}
