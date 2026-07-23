<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Functional;

use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ticket edit form validation and concurrency.
 */
#[Group('ticketdesk')]
class TicketFormFunctionalTest extends TicketdeskFunctionalTestBase {

  /**
   * Tests requesters can create tickets via the UI.
   */
  public function testRequesterCanCreateTicket(): void {
    $requester = $this->createRequesterUser();
    $this->drupalLogin($requester);

    $this->drupalGet('/ticketdesk/ticket/add');
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'title[0][value]' => 'New laptop request',
      'description[0][value]' => 'Need a replacement device.',
      'priority' => TicketInterface::PRIORITY_MEDIUM,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Created ticket New laptop request.');
    $this->assertSession()->pageTextContains('New laptop request');
    $this->assertSession()->pageTextContains('Description');
  }

  /**
   * Tests agents can edit tickets via the UI.
   */
  public function testAgentCanEditTicket(): void {
    $agent = $this->createAgentUser();
    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'title' => 'Editable ticket',
    ]);

    $this->drupalLogin($agent);
    $this->drupalGet('/ticketdesk/ticket/' . $ticket->id() . '/edit');

    $edit = [
      'title[0][value]' => 'Updated ticket title',
      'description[0][value]' => $ticket->getDescription(),
      'priority' => $ticket->getPriority(),
      'status' => TicketInterface::STATUS_IN_PROGRESS,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Updated ticket Updated ticket title.');
  }

  /**
   * Tests requesters cannot edit closed tickets.
   */
  public function testRequesterCannotEditClosedTicket(): void {
    $requester = $this->createRequesterUser();
    $agent = $this->createAgentUser();

    $this->drupalLogin($agent);
    $ticket = $this->createTicket([
      'uid' => $requester->id(),
      'assignee' => $agent->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);
    $ticket->setStatus(TicketInterface::STATUS_IN_PROGRESS);
    $ticket->save();
    $ticket->setStatus(TicketInterface::STATUS_RESOLVED);
    $ticket->save();
    $ticket->setStatus(TicketInterface::STATUS_CLOSED);
    $ticket->save();

    $this->drupalLogin($requester);
    $this->drupalGet('/ticketdesk/ticket/' . $ticket->id() . '/edit');
    $this->assertSession()->statusCodeEquals(403);
  }

}
