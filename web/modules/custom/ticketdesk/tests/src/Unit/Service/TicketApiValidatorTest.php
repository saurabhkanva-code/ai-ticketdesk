<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\ticketdesk\Service\TicketAccessService;
use Drupal\ticketdesk\Service\TicketApiValidator;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests API payload validation.
 */
#[CoversClass(TicketApiValidator::class)]
#[Group('ticketdesk')]
class TicketApiValidatorTest extends UnitTestCase {

  /**
   * The validator under test.
   */
  protected TicketApiValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new TicketApiValidator($this->createMock(TicketAccessService::class));
  }

  /**
   * Tests create validation requires title and description.
   */
  public function testValidateCreateRequiresCoreFields(): void {
    $errors = $this->validator->validateCreate([]);
    $this->assertContains('Title is required.', $errors);
    $this->assertContains('Description is required.', $errors);
  }

  /**
   * Tests create validation accepts a valid payload.
   */
  public function testValidateCreateAcceptsValidPayload(): void {
    $errors = $this->validator->validateCreate([
      'title' => 'Broken printer',
      'description' => 'It will not turn on.',
      'priority' => TicketInterface::PRIORITY_HIGH,
    ]);
    $this->assertSame([], $errors);
  }

  /**
   * Tests create validation rejects invalid priority.
   */
  public function testValidateCreateRejectsInvalidPriority(): void {
    $errors = $this->validator->validateCreate([
      'title' => 'Broken printer',
      'description' => 'It will not turn on.',
      'priority' => 'urgent',
    ]);
    $this->assertContains('Priority must be one of: low, medium, high, critical.', $errors);
  }

  /**
   * Tests description sanitization strips HTML.
   */
  public function testSanitizeDescriptionStripsHtml(): void {
    $sanitized = $this->validator->sanitizeDescription('<script>alert(1)</script>Help me');
    $this->assertSame('alert(1)Help me', $sanitized);
  }

  /**
   * Tests update validation requires changed timestamp.
   */
  public function testValidateUpdateRequiresChangedTimestamp(): void {
    $ticket = $this->createMock(TicketInterface::class);
    $account = $this->createMock(\Drupal\Core\Session\AccountInterface::class);

    $errors = $this->validator->validateUpdate([], $ticket, $account);
    $this->assertContains('The changed timestamp is required for updates.', $errors);
  }

  /**
   * Tests requesters cannot change priority on update.
   */
  public function testValidateUpdateRejectsPriorityChangeWithoutPermission(): void {
    $ticket = $this->createMock(TicketInterface::class);
    $account = $this->createMock(\Drupal\Core\Session\AccountInterface::class);
    $ticketAccess = $this->createMock(TicketAccessService::class);
    $ticketAccess->method('canManageTicketFields')->willReturn(FALSE);
    $validator = new TicketApiValidator($ticketAccess);

    $errors = $validator->validateUpdate([
      'changed' => 100,
      'priority' => TicketInterface::PRIORITY_HIGH,
    ], $ticket, $account);

    $this->assertContains('You do not have permission to change ticket priority.', $errors);
  }

  /**
   * Tests requesters cannot change status without permission.
   */
  public function testValidateUpdateRejectsStatusChangeWithoutPermission(): void {
    $ticket = $this->createMock(TicketInterface::class);
    $account = $this->createMock(\Drupal\Core\Session\AccountInterface::class);
    $account->method('hasPermission')->willReturnMap([
      ['administer tickets', FALSE],
      ['transition ticket', FALSE],
    ]);

    $errors = $this->validator->validateUpdate([
      'changed' => 100,
      'status' => TicketInterface::STATUS_IN_PROGRESS,
    ], $ticket, $account);

    $this->assertContains('You do not have permission to change ticket status.', $errors);
  }

}
