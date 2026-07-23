<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests presave validation for ticket transitions and permissions.
 */
#[Group('ticketdesk')]
#[RunTestsInSeparateProcesses]
class TicketPresaveKernelTest extends TicketdeskKernelTestBase {

  /**
   * Tests agents can apply valid status transitions on save.
   */
  public function testValidTransitionOnSave(): void {
    $agent = $this->createAgentUser();
    $this->setCurrentUser($agent);

    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);
    $ticket->setStatus(TicketInterface::STATUS_IN_PROGRESS);
    $ticket->save();

    $this->assertSame(TicketInterface::STATUS_IN_PROGRESS, $ticket->getStatus());
  }

  /**
   * Tests invalid transitions are rejected on save.
   */
  public function testInvalidTransitionOnSaveThrows(): void {
    $agent = $this->createAgentUser();
    $this->setCurrentUser($agent);

    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);
    $ticket->setStatus(TicketInterface::STATUS_CLOSED);

    $this->expectException(EntityStorageException::class);
    $ticket->save();
  }

}
