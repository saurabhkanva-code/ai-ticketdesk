<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountInterface;
use Drupal\ticketdesk\Service\TicketAccessService;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\TicketInterface;

/**
 * Validates ticket API request payloads.
 */
class TicketApiValidator {

  public function __construct(
    protected readonly TicketAccessService $ticketAccess,
  ) {}

  /**
   * Validates a create-ticket payload.
   *
   * @param array<string, mixed> $payload
   *
   * @return list<string>
   *   Validation error messages.
   */
  public function validateCreate(array $payload): array {
    $errors = [];

    $title = $this->stringValue($payload['title'] ?? NULL);
    if ($title === '') {
      $errors[] = 'Title is required.';
    }
    elseif (mb_strlen($title) > 255) {
      $errors[] = 'Title must not exceed 255 characters.';
    }

    $description = $this->sanitizeDescription($payload['description'] ?? NULL);
    if ($description === '') {
      $errors[] = 'Description is required.';
    }

    $priority = $this->stringValue($payload['priority'] ?? TicketInterface::PRIORITY_MEDIUM);
    if (!array_key_exists($priority, Ticket::getPriorityOptions())) {
      $errors[] = 'Priority must be one of: low, medium, high, critical.';
    }

    return $errors;
  }

  /**
   * Validates an update-ticket payload for the given account.
   *
   * @param array<string, mixed> $payload
   *
   * @return list<string>
   *   Validation error messages.
   */
  public function validateUpdate(array $payload, TicketInterface $ticket, AccountInterface $account): array {
    $errors = [];

    if (!array_key_exists('changed', $payload)) {
      $errors[] = 'The changed timestamp is required for updates.';
    }
    elseif (!is_int($payload['changed']) && !ctype_digit((string) $payload['changed'])) {
      $errors[] = 'The changed timestamp must be an integer.';
    }

    if (array_key_exists('title', $payload)) {
      $title = $this->stringValue($payload['title']);
      if ($title === '') {
        $errors[] = 'Title cannot be empty.';
      }
      elseif (mb_strlen($title) > 255) {
        $errors[] = 'Title must not exceed 255 characters.';
      }
    }

    if (array_key_exists('description', $payload)) {
      $description = $this->sanitizeDescription($payload['description']);
      if ($description === '') {
        $errors[] = 'Description cannot be empty.';
      }
    }

    $can_manage = $this->ticketAccess->canManageTicketFields($ticket, $account);

    if (array_key_exists('priority', $payload)) {
      if (!$can_manage) {
        $errors[] = 'You do not have permission to change ticket priority.';
      }
      else {
        $priority = $this->stringValue($payload['priority']);
        if (!array_key_exists($priority, Ticket::getPriorityOptions())) {
          $errors[] = 'Priority must be one of: low, medium, high, critical.';
        }
      }
    }

    if (array_key_exists('status', $payload)) {
      if (!$account->hasPermission('administer tickets')
        && !$account->hasPermission('transition ticket')) {
        $errors[] = 'You do not have permission to change ticket status.';
      }
      else {
        $status = $this->stringValue($payload['status']);
        if (!array_key_exists($status, Ticket::getStatusOptions())) {
          $errors[] = 'Status must be one of: open, in_progress, resolved, closed.';
        }
      }
    }

    if (array_key_exists('assignee', $payload)) {
      if (!$account->hasPermission('administer tickets')
        && !$account->hasPermission('assign ticket')) {
        $errors[] = 'You do not have permission to assign tickets.';
      }
      elseif ($payload['assignee'] !== NULL && !is_int($payload['assignee']) && !ctype_digit((string) $payload['assignee'])) {
        $errors[] = 'Assignee must be a user ID or null.';
      }
    }

    return $errors;
  }

  /**
   * Sanitizes a description value for plain-text storage.
   */
  public function sanitizeDescription(mixed $value): string {
    $description = $this->stringValue($value);
    return trim(Html::decodeEntities(strip_tags($description)));
  }

  /**
   * Casts a mixed value to a trimmed string.
   */
  public function stringValue(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    return trim((string) $value);
  }

}
