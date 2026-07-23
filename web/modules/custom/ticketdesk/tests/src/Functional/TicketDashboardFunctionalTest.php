<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Functional;

use Drupal\ticketdesk\Form\TicketDashboardFiltersForm;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ticket dashboard page.
 */
#[Group('ticketdesk')]
class TicketDashboardFunctionalTest extends TicketdeskFunctionalTestBase {

  /**
   * Tests agents only see tickets they are assigned to.
   */
  public function testAgentSeesOnlyInvolvedTickets(): void {
    $agent = $this->createAgentUser();
    $requester = $this->createRequesterUser();

    $visible_ticket = $this->createTicket([
      'title' => 'Assigned to agent',
      'uid' => $requester->id(),
      'assignee' => $agent->id(),
    ]);
    $this->createTicket([
      'title' => 'Unrelated ticket',
      'uid' => $requester->id(),
    ]);

    $this->drupalLogin($agent);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Assigned to agent');
    $this->assertSession()->pageTextNotContains('Unrelated ticket');
    $this->assertSession()->pageTextContains((string) $visible_ticket->id());
    $this->assertSession()->buttonNotExists('Apply');
  }

  /**
   * Tests admins can see all tickets and use filters.
   */
  public function testAdminSeesAllTicketsAndFilters(): void {
    $admin = $this->createAdminUser();
    $requester = $this->createRequesterUser();

    $this->createTicket([
      'title' => 'Assigned ticket',
      'uid' => $requester->id(),
    ]);
    $this->createTicket([
      'title' => 'Another ticket',
      'uid' => $requester->id(),
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Assigned ticket');
    $this->assertSession()->pageTextContains('Another ticket');
    $this->assertSession()->pageTextContains('Requester');
    $this->assertSession()->buttonExists('Apply');
  }

  /**
   * Tests admins can filter tickets by status on the dashboard.
   */
  public function testAdminCanFilterDashboardByStatus(): void {
    $admin = $this->createAdminUser();
    $requester = $this->createRequesterUser();

    $this->createTicket([
      'title' => 'Open ticket',
      'uid' => $requester->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);
    $this->createTicket([
      'title' => 'Closed ticket',
      'uid' => $requester->id(),
      'status' => TicketInterface::STATUS_CLOSED,
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('/ticketdesk/dashboard', [
      'query' => [
        'status' => TicketInterface::STATUS_CLOSED,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Closed ticket');
    $this->assertSession()->pageTextNotContains('Open ticket');
  }

  /**
   * Tests admins can filter unassigned tickets on the dashboard.
   */
  public function testAdminCanFilterUnassignedTickets(): void {
    $admin = $this->createAdminUser();
    $requester = $this->createRequesterUser();
    $agent = $this->createAgentUser();

    $this->createTicket([
      'title' => 'Needs assignment',
      'uid' => $requester->id(),
    ]);
    $this->createTicket([
      'title' => 'Already assigned',
      'uid' => $requester->id(),
      'assignee' => $agent->id(),
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('/ticketdesk/dashboard', [
      'query' => [
        'assignee' => TicketDashboardFiltersForm::ASSIGNEE_UNASSIGNED,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Needs assignment');
    $this->assertSession()->pageTextNotContains('Already assigned');
  }

  /**
   * Tests admins can filter tickets via the dashboard form submit.
   */
  public function testAdminCanFilterDashboardViaFormSubmit(): void {
    $admin = $this->createAdminUser();
    $requester = $this->createRequesterUser();

    $this->createTicket([
      'title' => 'Open via form',
      'uid' => $requester->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);
    $this->createTicket([
      'title' => 'Closed via form',
      'uid' => $requester->id(),
      'status' => TicketInterface::STATUS_CLOSED,
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->submitForm([
      'filters[status]' => TicketInterface::STATUS_CLOSED,
    ], 'Apply');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Closed via form');
    $this->assertSession()->pageTextNotContains('Open via form');
  }

  /**
   * Tests requesters can access the dashboard and see their own tickets.
   */
  public function testRequesterSeesOwnTicketsOnDashboard(): void {
    $requester = $this->createRequesterUser();
    $other = $this->createRequesterUser();

    $this->createTicket([
      'title' => 'My open ticket',
      'uid' => $requester->id(),
    ]);
    $this->createTicket([
      'title' => 'Someone else ticket',
      'uid' => $other->id(),
    ]);

    $this->drupalLogin($requester);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My open ticket');
    $this->assertSession()->pageTextNotContains('Someone else ticket');
    $this->assertSession()->pageTextContains('Take Action');
    $this->assertSession()->pageTextContains('Edit ticket');
    $this->assertSession()->linkExists('Create ticket');
    $this->assertSession()->buttonNotExists('Apply');
  }

  /**
   * Tests requesters can see tickets assigned to them.
   */
  public function testRequesterSeesAssignedTicketsOnDashboard(): void {
    $requester = $this->createRequesterUser();
    $other = $this->createRequesterUser();

    $this->createTicket([
      'title' => 'Assigned to me',
      'uid' => $other->id(),
      'assignee' => $requester->id(),
    ]);
    $this->createTicket([
      'title' => 'Not mine',
      'uid' => $other->id(),
    ]);

    $this->drupalLogin($requester);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Assigned to me');
    $this->assertSession()->pageTextNotContains('Not mine');
  }

  /**
   * Tests dashboard shows empty state when a requester has no tickets.
   */
  public function testRequesterDashboardEmptyState(): void {
    $requester = $this->createRequesterUser();
    $this->drupalLogin($requester);
    $this->drupalGet('/ticketdesk/dashboard');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No tickets found.');
  }

}
