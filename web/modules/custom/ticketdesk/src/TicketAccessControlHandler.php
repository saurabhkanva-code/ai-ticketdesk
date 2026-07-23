<?php

declare(strict_types=1);

namespace Drupal\ticketdesk;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ticketdesk\Service\TicketAccessService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for tickets.
 */
class TicketAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly TicketAccessService $ticketAccess,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get(TicketAccessService::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    assert($entity instanceof TicketInterface);

    if ($account->isAnonymous()) {
      return AccessResult::forbidden('Anonymous users cannot access tickets.')
        ->addCacheContexts(['user.roles:anonymous']);
    }

    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'update' => $this->checkUpdateAccess($entity, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
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
    return AccessResult::allowedIf($this->ticketAccess->canView($ticket, $account))
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($ticket);
  }

  /**
   * Checks update access for a ticket.
   */
  protected function checkUpdateAccess(TicketInterface $ticket, AccountInterface $account): AccessResult {
    if (!$this->ticketAccess->canUpdate($ticket, $account)) {
      $message = (int) $ticket->getOwnerId() === (int) $account->id()
        && $ticket->getStatus() === TicketInterface::STATUS_CLOSED
        ? 'Closed tickets cannot be edited by requesters.'
        : 'You do not have permission to edit this ticket.';

      return AccessResult::forbidden($message)
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($ticket);
    }

    return AccessResult::allowed()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($ticket);
  }

  /**
   * Checks delete access for a ticket.
   */
  protected function checkDeleteAccess(TicketInterface $ticket, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($this->ticketAccess->canAdminister($account))
      ->cachePerPermissions()
      ->addCacheableDependency($ticket);
  }

}
