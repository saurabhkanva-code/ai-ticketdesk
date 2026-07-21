<?php

declare(strict_types=1);

namespace Drupal\ticketdesk;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for Ticket entities.
 */
interface TicketInterface extends ContentEntityInterface, EntityOwnerInterface {

  public const STATUS_OPEN = 'open';

  public const STATUS_IN_PROGRESS = 'in_progress';

  public const STATUS_RESOLVED = 'resolved';

  public const STATUS_CLOSED = 'closed';

  public const PRIORITY_LOW = 'low';

  public const PRIORITY_MEDIUM = 'medium';

  public const PRIORITY_HIGH = 'high';

  public const PRIORITY_CRITICAL = 'critical';

  /**
   * Gets the ticket title.
   */
  public function getTitle(): string;

  /**
   * Sets the ticket title.
   */
  public function setTitle(string $title): static;

  /**
   * Gets the ticket description.
   */
  public function getDescription(): string;

  /**
   * Sets the ticket description.
   */
  public function setDescription(string $description): static;

  /**
   * Gets the ticket priority.
   */
  public function getPriority(): string;

  /**
   * Sets the ticket priority.
   */
  public function setPriority(string $priority): static;

  /**
   * Gets the ticket status.
   */
  public function getStatus(): string;

  /**
   * Sets the ticket status.
   */
  public function setStatus(string $status): static;

  /**
   * Gets the assignee user ID.
   */
  public function getAssigneeId(): ?int;

  /**
   * Sets the assignee user ID.
   */
  public function setAssigneeId(?int $uid): static;

}
