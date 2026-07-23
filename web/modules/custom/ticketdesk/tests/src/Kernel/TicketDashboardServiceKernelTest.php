<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Kernel;

use Drupal\ticketdesk\Service\TicketDashboardService;
use Drupal\ticketdesk\TicketInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests dashboard aggregate counts.
 */
#[Group('ticketdesk')]
#[RunTestsInSeparateProcesses]
class TicketDashboardServiceKernelTest extends TicketdeskKernelTestBase {

  /**
   * The dashboard service under test.
   */
  protected TicketDashboardService $dashboardService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dashboardService = $this->container->get(TicketDashboardService::class);
    $this->setCurrentUser($this->createAdminUser());
  }

  /**
   * Tests counts are zero-filled when no tickets exist.
   */
  public function testCountsAreZeroFilledWithNoTickets(): void {
    $status_counts = $this->dashboardService->getCountsByStatus();
    $priority_counts = $this->dashboardService->getCountsByPriority();

    $this->assertSame(0, array_sum($status_counts));
    $this->assertSame(0, array_sum($priority_counts));
    $this->assertArrayHasKey(TicketInterface::STATUS_OPEN, $status_counts);
    $this->assertArrayHasKey(TicketInterface::PRIORITY_MEDIUM, $priority_counts);
  }

  /**
   * Tests counts reflect saved tickets.
   */
  public function testCountsReflectSavedTickets(): void {
    $owner = $this->createRequesterUser();

    $this->createTicket([
      'uid' => $owner->id(),
      'status' => TicketInterface::STATUS_OPEN,
      'priority' => TicketInterface::PRIORITY_HIGH,
    ]);
    $this->createTicket([
      'uid' => $owner->id(),
      'status' => TicketInterface::STATUS_OPEN,
      'priority' => TicketInterface::PRIORITY_LOW,
    ]);
    $this->createTicket([
      'uid' => $owner->id(),
      'status' => TicketInterface::STATUS_RESOLVED,
      'priority' => TicketInterface::PRIORITY_HIGH,
    ]);

    $status_counts = $this->dashboardService->getCountsByStatus();
    $priority_counts = $this->dashboardService->getCountsByPriority();

    $this->assertSame(2, $status_counts[TicketInterface::STATUS_OPEN]);
    $this->assertSame(1, $status_counts[TicketInterface::STATUS_RESOLVED]);
    $this->assertSame(2, $priority_counts[TicketInterface::PRIORITY_HIGH]);
    $this->assertSame(1, $priority_counts[TicketInterface::PRIORITY_LOW]);
  }

  /**
   * Tests dashboard cache tags include the list tag.
   */
  public function testGetCacheTags(): void {
    $this->assertSame(
      [TicketDashboardService::LIST_CACHE_TAG],
      $this->dashboardService->getCacheTags(),
    );
  }

}
