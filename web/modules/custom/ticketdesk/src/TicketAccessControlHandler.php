<?php

declare(strict_types=1);

namespace Drupal\ticketdesk;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for tickets.
 */
class TicketAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    assert($entity instanceof TicketInterface);

    if ($account->isAnonymous()) {
      return AccessResult::forbidden('Anonymous users cannot access tickets.')
        ->addCacheContexts(['user.roles:anonymous']);
    }

    if ($account->hasPermission('administer tickets')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'update' => $this->checkUpdateAccess($entity, $account),
      'delete' => AccessResult::allowedIfHasPermission($account, 'administer tickets'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('Anonymous users cannot create tickets.')
        ->addCacheContexts(['user.roles:anonymous']);
    }

    return AccessResult::allowedIfHasPermission($account, 'create ticket');
  }

  /**
   * Checks view access for a ticket.
   */
  protected function checkViewAccess(TicketInterface $ticket, AccountInterface $account): AccessResult {
    if ($account->hasPermission('view any ticket')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = (int) $ticket->getOwnerId() === (int) $account->id();
    return AccessResult::allowedIf($is_owner && $account->hasPermission('view own ticket'))
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($ticket);
  }

  /**
   * Checks update access for a ticket.
   */
  protected function checkUpdateAccess(TicketInterface $ticket, AccountInterface $account): AccessResult {
    if ($account->hasPermission('edit any ticket')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = (int) $ticket->getOwnerId() === (int) $account->id();
    return AccessResult::allowedIf($is_owner && $account->hasPermission('create ticket'))
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($ticket);
  }

}
