<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Kernel;

use Drupal\ticketdesk\Service\TicketTransitionService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests optimistic concurrency checks against ticket entities.
 */
#[Group('ticketdesk')]
#[RunTestsInSeparateProcesses]
class TicketConcurrencyKernelTest extends TicketdeskKernelTestBase {

  /**
   * Tests stale changed timestamps are rejected.
   */
  public function testAssertConcurrentSafeDetectsStaleTimestamp(): void {
    $ticket = $this->createTicket();
    $service = $this->container->get(TicketTransitionService::class);

    $error = $service->assertConcurrentSafe($ticket, $ticket->getChangedTime() - 100);
    $this->assertNotNull($error);
    $this->assertStringContainsString('modified by another user', $error);
  }

  /**
   * Tests matching changed timestamps pass concurrency checks.
   */
  public function testAssertConcurrentSafeAllowsMatchingTimestamp(): void {
    $ticket = $this->createTicket();
    $service = $this->container->get(TicketTransitionService::class);

    $this->assertNull($service->assertConcurrentSafe($ticket, $ticket->getChangedTime()));
  }

}
