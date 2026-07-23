<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Service;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ticketdesk\TicketInterface;

/**
 * Centralizes ticket access rules for queries, entities, and UI.
 */
class TicketAccessService {

  /**
   * Whether the account has full ticket administration rights.
   */
  public function canAdminister(AccountInterface $account): bool {
    return $account->hasPermission('administer tickets');
  }

  /**
   * Whether the account can view all tickets (dashboard/API list scope).
   */
  public function canViewAll(AccountInterface $account): bool {
    return $this->canAdminister($account);
  }

  /**
   * Whether the account created the ticket or is assigned to it.
   */
  public function isInvolved(TicketInterface $ticket, AccountInterface $account): bool {
    return (int) $ticket->getOwnerId() === (int) $account->id()
      || (int) $ticket->getAssigneeId() === (int) $account->id();
  }

  /**
   * Whether the account can view a ticket.
   */
  public function canView(TicketInterface $ticket, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    if ($this->canAdminister($account)) {
      return TRUE;
    }
    return $this->isInvolved($ticket, $account);
  }

  /**
   * Whether the account can update a ticket.
   */
  public function canUpdate(TicketInterface $ticket, AccountInterface $account): bool {
    if ($this->canAdminister($account)) {
      return TRUE;
    }

    if ((int) $ticket->getAssigneeId() === (int) $account->id()) {
      return TRUE;
    }

    if ((int) $ticket->getOwnerId() === (int) $account->id()) {
      if ($ticket->getStatus() === TicketInterface::STATUS_CLOSED) {
        return FALSE;
      }
      return $account->hasPermission('create ticket');
    }

    return FALSE;
  }

  /**
   * Whether the account can manage agent-level ticket fields.
   */
  public function canManageTicketFields(TicketInterface $ticket, AccountInterface $account): bool {
    if ($this->canAdminister($account)) {
      return TRUE;
    }

    $is_involved = $this->isInvolved($ticket, $account);
    if (!$is_involved) {
      return FALSE;
    }

    return $account->hasPermission('transition ticket')
      || $account->hasPermission('assign ticket');
  }

  /**
   * Limits a ticket query to tickets the account created or is assigned to.
   */
  public function applyInvolvementConditions(QueryInterface $query, AccountInterface $account): void {
    $involvement = $query->orConditionGroup()
      ->condition('uid', $account->id())
      ->condition('assignee', $account->id());
    $query->condition($involvement);
  }

}
