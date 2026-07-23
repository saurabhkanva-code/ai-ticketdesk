<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ticket JSON API endpoints.
 */
#[Group('ticketdesk')]
class TicketApiFunctionalTest extends TicketdeskFunctionalTestBase {

  /**
   * Tests anonymous users cannot list tickets.
   */
  public function testAnonymousListDenied(): void {
    $response = $this->apiRequest('GET', '/api/ticketdesk/tickets');
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Tests requesters can create and list their own tickets.
   */
  public function testRequesterCreateAndList(): void {
    $requester = $this->createRequesterUser();
    $this->drupalLogin($requester);

    $response = $this->apiRequest('POST', '/api/ticketdesk/tickets', [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => Json::encode([
        'title' => 'VPN issue',
        'description' => 'Cannot connect remotely.',
        'priority' => TicketInterface::PRIORITY_HIGH,
      ]),
    ]);
    $this->assertSame(201, $response->getStatusCode());
    $payload = $this->decodeResponse($response);
    $this->assertSame('VPN issue', $payload['data']['title']);
    $this->assertSame(TicketInterface::STATUS_OPEN, $payload['data']['status']);

    $list_response = $this->apiRequest('GET', '/api/ticketdesk/tickets');
    $this->assertSame(200, $list_response->getStatusCode());
    $list_payload = $this->decodeResponse($list_response);
    $this->assertSame(1, $list_payload['meta']['count']);
    $this->assertSame('VPN issue', $list_payload['data'][0]['title']);
  }

  /**
   * Tests invalid status transitions return HTTP 422.
   */
  public function testInvalidTransitionReturns422(): void {
    $agent = $this->createAgentUser();
    $this->drupalLogin($agent);

    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);

    $response = $this->apiRequest('PATCH', '/api/ticketdesk/tickets/' . $ticket->id(), [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => Json::encode([
        'changed' => $ticket->getChangedTime(),
        'status' => TicketInterface::STATUS_CLOSED,
      ]),
    ]);

    $this->assertSame(422, $response->getStatusCode());
    $payload = $this->decodeResponse($response);
    $this->assertStringContainsString('Cannot transition', $payload['message']);
  }

  /**
   * Tests stale changed timestamps return HTTP 409.
   */
  public function testConcurrencyConflictReturns409(): void {
    $agent = $this->createAgentUser();
    $this->drupalLogin($agent);

    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'title' => 'Original title',
    ]);

    $response = $this->apiRequest('PATCH', '/api/ticketdesk/tickets/' . $ticket->id(), [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => Json::encode([
        'changed' => $ticket->getChangedTime() - 100,
        'title' => 'Updated title',
      ]),
    ]);

    $this->assertSame(409, $response->getStatusCode());
    $payload = $this->decodeResponse($response);
    $this->assertStringContainsString('modified by another user', $payload['message']);
  }

  /**
   * Tests requesters cannot view another user's ticket.
   */
  public function testRequesterCannotViewOthersTicket(): void {
    $owner = $this->createRequesterUser();
    $other = $this->createRequesterUser();
    $ticket = $this->createTicket(['uid' => $owner->id()]);

    $this->drupalLogin($other);
    $response = $this->apiRequest('GET', '/api/ticketdesk/tickets/' . $ticket->id());
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Tests agents can transition tickets through the lifecycle.
   */
  public function testAgentValidTransition(): void {
    $agent = $this->createAgentUser();
    $this->drupalLogin($agent);

    $ticket = $this->createTicket([
      'uid' => $agent->id(),
      'status' => TicketInterface::STATUS_OPEN,
    ]);

    $response = $this->apiRequest('PATCH', '/api/ticketdesk/tickets/' . $ticket->id(), [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => Json::encode([
        'changed' => $ticket->getChangedTime(),
        'status' => TicketInterface::STATUS_IN_PROGRESS,
      ]),
    ]);

    $this->assertSame(200, $response->getStatusCode());
    $payload = $this->decodeResponse($response);
    $this->assertSame(TicketInterface::STATUS_IN_PROGRESS, $payload['data']['status']);
  }

  /**
   * Tests fetching a nonexistent ticket returns HTTP 404.
   */
  public function testMissingTicketReturns404(): void {
    $agent = $this->createAgentUser();
    $this->drupalLogin($agent);

    $response = $this->apiRequest('GET', '/api/ticketdesk/tickets/99999');
    $this->assertSame(404, $response->getStatusCode());
  }

}
