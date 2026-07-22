<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\ticketdesk\TicketInterface;

/**
 * Normalizes ticket entities to and from API payloads.
 */
class TicketApiNormalizer {

  /**
   * Converts a ticket entity to an API response array.
   *
   * @return array<string, mixed>
   */
  public function normalize(TicketInterface $ticket): array {
    $assignee = $ticket->get('assignee')->entity;

    return [
      'id' => (int) $ticket->id(),
      'uuid' => $ticket->uuid(),
      'title' => $ticket->getTitle(),
      'description' => $ticket->getDescription(),
      'priority' => $ticket->getPriority(),
      'status' => $ticket->getStatus(),
      'assignee' => $assignee ? $ticket->getAssigneeId() : NULL,
      'requester' => $ticket->getOwnerId() !== NULL ? (int) $ticket->getOwnerId() : NULL,
      'created' => (int) $ticket->get('created')->value,
      'changed' => $ticket->getChangedTime(),
    ];
  }

  /**
   * Normalizes a list of tickets.
   *
   * @param \Drupal\ticketdesk\TicketInterface[] $tickets
   *
   * @return list<array<string, mixed>>
   */
  public function normalizeList(array $tickets): array {
    return array_map(fn (TicketInterface $ticket): array => $this->normalize($ticket), $tickets);
  }

}
