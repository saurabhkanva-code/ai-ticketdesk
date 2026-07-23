<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\Service\TicketTransitionService;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ticket status transition service.
 */
#[CoversClass(TicketTransitionService::class)]
#[Group('ticketdesk')]
class TicketTransitionServiceTest extends UnitTestCase {

  /**
   * The transition service under test.
   */
  protected TicketTransitionService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->service = new TicketTransitionService($this->getStringTranslationStub());
  }

  /**
   * Tests allowed transitions for each status.
   */
  #[DataProvider('allowedTransitionsProvider')]
  public function testGetAllowedTransitions(string $current, array $expected): void {
    $this->assertSame($expected, $this->service->getAllowedTransitions($current));
  }

  /**
   * Data provider for allowed transitions.
   *
   * @return array<string, array{0: string, 1: list<string>}>
   */
  public static function allowedTransitionsProvider(): array {
    return [
      'open' => [TicketInterface::STATUS_OPEN, [TicketInterface::STATUS_IN_PROGRESS]],
      'in progress' => [TicketInterface::STATUS_IN_PROGRESS, [TicketInterface::STATUS_RESOLVED]],
      'resolved' => [
        TicketInterface::STATUS_RESOLVED,
        [TicketInterface::STATUS_CLOSED, TicketInterface::STATUS_OPEN],
      ],
      'closed' => [TicketInterface::STATUS_CLOSED, []],
    ];
  }

  /**
   * Tests valid transitions return no error.
   */
  #[DataProvider('validTransitionProvider')]
  public function testValidateTransitionAllowsValidMoves(string $current, string $new): void {
    $this->assertNull($this->service->validateTransition($current, $new));
  }

  /**
   * Data provider for valid transitions.
   *
   * @return array<string, array{0: string, 1: string}>
   */
  public static function validTransitionProvider(): array {
    return [
      'same status' => [TicketInterface::STATUS_OPEN, TicketInterface::STATUS_OPEN],
      'open to in progress' => [TicketInterface::STATUS_OPEN, TicketInterface::STATUS_IN_PROGRESS],
      'in progress to resolved' => [TicketInterface::STATUS_IN_PROGRESS, TicketInterface::STATUS_RESOLVED],
      'resolved to closed' => [TicketInterface::STATUS_RESOLVED, TicketInterface::STATUS_CLOSED],
      'resolved to open reopen' => [TicketInterface::STATUS_RESOLVED, TicketInterface::STATUS_OPEN],
    ];
  }

  /**
   * Tests invalid transitions return an error message.
   */
  #[DataProvider('invalidTransitionProvider')]
  public function testValidateTransitionRejectsInvalidMoves(string $current, string $new): void {
    $error = $this->service->validateTransition($current, $new);
    $this->assertNotNull($error);
    $this->assertNotSame('', $error);
  }

  /**
   * Data provider for invalid transitions.
   *
   * @return array<string, array{0: string, 1: string}>
   */
  public static function invalidTransitionProvider(): array {
    return [
      'open to closed' => [TicketInterface::STATUS_OPEN, TicketInterface::STATUS_CLOSED],
      'open to resolved' => [TicketInterface::STATUS_OPEN, TicketInterface::STATUS_RESOLVED],
      'in progress to open' => [TicketInterface::STATUS_IN_PROGRESS, TicketInterface::STATUS_OPEN],
      'closed to open' => [TicketInterface::STATUS_CLOSED, TicketInterface::STATUS_OPEN],
      'closed to in progress' => [TicketInterface::STATUS_CLOSED, TicketInterface::STATUS_IN_PROGRESS],
    ];
  }

  /**
   * Tests applyTransition updates the ticket when valid.
   */
  public function testApplyTransitionUpdatesTicket(): void {
    $ticket = $this->createMock(TicketInterface::class);
    $ticket->method('getStatus')->willReturn(TicketInterface::STATUS_OPEN);
    $ticket->expects($this->once())
      ->method('setStatus')
      ->with(TicketInterface::STATUS_IN_PROGRESS);

    $this->service->applyTransition($ticket, TicketInterface::STATUS_IN_PROGRESS);
  }

  /**
   * Tests applyTransition throws when invalid.
   */
  public function testApplyTransitionThrowsForInvalidMove(): void {
    $ticket = $this->createMock(TicketInterface::class);
    $ticket->method('getStatus')->willReturn(TicketInterface::STATUS_OPEN);

    $this->expectException(\InvalidArgumentException::class);
    $this->service->applyTransition($ticket, TicketInterface::STATUS_CLOSED);
  }

  /**
   * Tests closed ticket transition error mentions terminal state.
   */
  public function testValidateTransitionFromClosedIsTerminal(): void {
    $error = $this->service->validateTransition(
      TicketInterface::STATUS_CLOSED,
      TicketInterface::STATUS_OPEN,
    );
    $this->assertNotNull($error);
    $this->assertStringContainsString(Ticket::getStatusOptions()[TicketInterface::STATUS_CLOSED], $error);
  }

}
