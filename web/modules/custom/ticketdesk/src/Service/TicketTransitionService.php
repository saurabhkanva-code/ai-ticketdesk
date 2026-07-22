<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\TicketInterface;

/**
 * Validates and applies ticket status transitions.
 */
class TicketTransitionService {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Allowed status transitions keyed by current status.
   *
   * @var array<string, list<string>>
   */
  private const TRANSITIONS = [
    TicketInterface::STATUS_OPEN => [TicketInterface::STATUS_IN_PROGRESS],
    TicketInterface::STATUS_IN_PROGRESS => [TicketInterface::STATUS_RESOLVED],
    TicketInterface::STATUS_RESOLVED => [
      TicketInterface::STATUS_CLOSED,
      TicketInterface::STATUS_OPEN,
    ],
    TicketInterface::STATUS_CLOSED => [],
  ];

  /**
   * Returns allowed next statuses for a current status.
   *
   * @return list<string>
   */
  public function getAllowedTransitions(string $current): array {
    return self::TRANSITIONS[$current] ?? [];
  }

  /**
   * Validates a status transition.
   *
   * @return string|null
   *   An error message when invalid, NULL when valid.
   */
  public function validateTransition(string $current, string $new): ?string {
    if ($current === $new) {
      return NULL;
    }

    $allowed = $this->getAllowedTransitions($current);
    if (in_array($new, $allowed, TRUE)) {
      return NULL;
    }

    $current_label = Ticket::getStatusOptions()[$current] ?? $current;
    $new_label = Ticket::getStatusOptions()[$new] ?? $new;

    if ($allowed === []) {
      return (string) $this->t('Cannot change the status of a @status ticket.', [
        '@status' => $current_label,
      ]);
    }

    $allowed_labels = array_map(
      static fn (string $status): string => Ticket::getStatusOptions()[$status] ?? $status,
      $allowed,
    );

    return (string) $this->t('Cannot transition from @from to @to. Allowed transitions: @allowed.', [
      '@from' => $current_label,
      '@to' => $new_label,
      '@allowed' => implode(', ', $allowed_labels),
    ]);
  }

  /**
   * Validates a transition for a ticket entity.
   */
  public function validateTransitionForTicket(TicketInterface $ticket, string $newStatus): ?string {
    return $this->validateTransition($ticket->getStatus(), $newStatus);
  }

  /**
   * Applies a validated status transition to a ticket.
   *
   * @throws \InvalidArgumentException
   *   When the transition is not allowed.
   */
  public function applyTransition(TicketInterface $ticket, string $newStatus): void {
    $error = $this->validateTransition($ticket->getStatus(), $newStatus);
    if ($error !== NULL) {
      throw new \InvalidArgumentException($error);
    }
    $ticket->setStatus($newStatus);
  }

  /**
   * Checks optimistic concurrency using the changed timestamp.
   *
   * @return string|null
   *   An error message when stale, NULL when safe.
   */
  public function assertConcurrentSafe(TicketInterface $ticket, int $expectedChanged): ?string {
    if ($ticket->getChangedTime() !== $expectedChanged) {
      return (string) $this->t('This ticket was modified by another user. Please reload and try again.');
    }
    return NULL;
  }

}
