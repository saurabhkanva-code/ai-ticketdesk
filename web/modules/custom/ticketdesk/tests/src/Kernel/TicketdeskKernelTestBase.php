<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ticketdesk\Traits\TicketTestTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for ticketdesk kernel tests.
 */
#[RunTestsInSeparateProcesses]
abstract class TicketdeskKernelTestBase extends KernelTestBase {

  use TicketTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'ticketdesk',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('ticket');
    $this->installConfig(['ticketdesk']);
  }

  /**
   * Switches the active account for subsequent operations.
   */
  protected function setCurrentUser(User $account): void {
    $this->container->get('account_switcher')->switchTo($account);
  }

}
