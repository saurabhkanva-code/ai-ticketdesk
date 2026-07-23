<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ticket entity access control.
 */
#[Group('ticketdesk')]
#[RunTestsInSeparateProcesses]
class TicketAccessKernelTest extends TicketdeskKernelTestBase {

  /**
   * Tests anonymous users cannot access tickets.
   */
  public function testAnonymousAccessDenied(): void {
    $owner = $this->createRequesterUser();
    $ticket = $this->createTicket(['uid' => $owner->id()]);

    $anonymous = User::getAnonymousUser();
    $this->assertFalse($ticket->access('view', $anonymous));
    $this->assertFalse($ticket->access('update', $anonymous));
    $this->assertFalse(
      $this->container->get('entity_type.manager')
        ->getAccessControlHandler('ticket')
        ->createAccess(NULL, $anonymous)
    );
  }

  /**
   * Tests requesters can view and edit their own open tickets.
   */
  public function testRequesterOwnTicketAccess(): void {
    $requester = $this->createRequesterUser();
    $ticket = $this->createTicket(['uid' => $requester->id()]);

    $this->assertTrue($ticket->access('view', $requester));
    $this->assertTrue($ticket->access('update', $requester));
  }

  /**
   * Tests requesters cannot view another user's ticket.
   */
  public function testRequesterCannotViewOthersTicket(): void {
    $owner = $this->createRequesterUser();
    $other = $this->createRequesterUser();
    $ticket = $this->createTicket(['uid' => $owner->id()]);

    $this->assertFalse($ticket->access('view', $other));
    $this->assertFalse($ticket->access('update', $other));
  }

  /**
   * Tests agents can access tickets assigned to them.
   */
  public function testAgentCanAccessAssignedTicket(): void {
    $owner = $this->createRequesterUser();
    $agent = $this->createAgentUser();
    $ticket = $this->createTicket([
      'uid' => $owner->id(),
      'assignee' => $agent->id(),
    ]);

    $this->assertTrue($ticket->access('view', $agent));
    $this->assertTrue($ticket->access('update', $agent));
  }

  /**
   * Tests agents cannot access unrelated tickets.
   */
  public function testAgentCannotAccessUnrelatedTicket(): void {
    $owner = $this->createRequesterUser();
    $agent = $this->createAgentUser();
    $ticket = $this->createTicket(['uid' => $owner->id()]);

    $this->assertFalse($ticket->access('view', $agent));
    $this->assertFalse($ticket->access('update', $agent));
  }

  /**
   * Tests admins can manage any ticket.
   */
  public function testAdminCanManageAnyTicket(): void {
    $owner = $this->createRequesterUser();
    $admin = $this->createAdminUser();
    $ticket = $this->createTicket(['uid' => $owner->id()]);

    $this->assertTrue($ticket->access('view', $admin));
    $this->assertTrue($ticket->access('update', $admin));
    $this->assertTrue($ticket->access('delete', $admin));
  }

}
