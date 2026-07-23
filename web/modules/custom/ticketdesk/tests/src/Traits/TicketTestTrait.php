<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Traits;

use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\TicketInterface;
use Drupal\user\Entity\User;

/**
 * Provides helpers for ticket-related tests.
 */
trait TicketTestTrait {

  /**
   * Creates a user with the ticketdesk requester role.
   */
  protected function createRequesterUser(array $values = []): User {
    return $this->createTicketdeskUser('ticketdesk_requester', $values);
  }

  /**
   * Creates a user with the ticketdesk agent role.
   */
  protected function createAgentUser(array $values = []): User {
    return $this->createTicketdeskUser('ticketdesk_agent', $values);
  }

  /**
   * Creates a user with the ticketdesk admin role.
   */
  protected function createAdminUser(array $values = []): User {
    return $this->createTicketdeskUser('ticketdesk_admin', $values);
  }

  /**
   * Creates a ticket entity with sensible defaults.
   *
   * @param array<string, mixed> $values
   */
  protected function createTicket(array $values = []): TicketInterface {
    $defaults = [
      'title' => 'Test ticket',
      'description' => [
        'value' => 'Test description',
        'format' => 'plain_text',
      ],
      'priority' => TicketInterface::PRIORITY_MEDIUM,
      'status' => TicketInterface::STATUS_OPEN,
    ];

    /** @var \Drupal\ticketdesk\TicketInterface $ticket */
    $ticket = Ticket::create($values + $defaults);
    $ticket->save();
    return $ticket;
  }

  /**
   * Reloads a ticket from storage.
   */
  protected function reloadTicket(int|string $id): TicketInterface {
    $ticket = \Drupal::entityTypeManager()->getStorage('ticket')->load($id);
    $this->assertNotNull($ticket);
    return $ticket;
  }

  /**
   * Creates a user assigned to a ticketdesk role.
   *
   * @param array<string, mixed> $values
   */
  private function createTicketdeskUser(string $role_id, array $values = []): User {
    $user = User::create($values + [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
      'roles' => [$role_id],
    ]);
    $user->save();
    return $user;
  }

}
