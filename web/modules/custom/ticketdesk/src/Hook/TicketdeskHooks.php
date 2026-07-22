<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\ticketdesk\Service\TicketDashboardService;
use Drupal\ticketdesk\Service\TicketTransitionService;
use Drupal\ticketdesk\TicketInterface;

/**
 * Hook implementations for Ticket Desk.
 */
class TicketdeskHooks {

  public function __construct(
    protected readonly TicketTransitionService $transitionService,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof TicketInterface || $entity->isNew()) {
      return;
    }

    $original = $entity->getOriginal();
    if (!$original instanceof TicketInterface) {
      return;
    }

    if ($entity->getStatus() !== $original->getStatus()) {
      if (!$this->currentUser->hasPermission('administer tickets')
        && !$this->currentUser->hasPermission('transition ticket')) {
        throw new EntityStorageException('You do not have permission to change ticket status.');
      }

      $error = $this->transitionService->validateTransition(
        $original->getStatus(),
        $entity->getStatus(),
      );
      if ($error !== NULL) {
        throw new EntityStorageException($error);
      }
    }

    if ($entity->getPriority() !== $original->getPriority()) {
      if (!$this->currentUser->hasPermission('administer tickets')
        && !$this->currentUser->hasPermission('edit any ticket')) {
        throw new EntityStorageException('You do not have permission to change ticket priority.');
      }
    }

    if ($entity->getAssigneeId() !== $original->getAssigneeId()) {
      if (!$this->currentUser->hasPermission('administer tickets')
        && !$this->currentUser->hasPermission('assign ticket')) {
        throw new EntityStorageException('You do not have permission to assign tickets.');
      }
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->invalidateDashboardCache($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->invalidateDashboardCache($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->invalidateDashboardCache($entity);
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() === 'ticketdesk.dashboard') {
      $attachments['#attached']['library'][] = 'ticketdesk/dashboard';
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ticket' => [
        'render element' => 'elements',
      ],
    ];
  }

  /**
   * Implements hook_template_preprocess_ticket().
   */
  #[Hook('template_preprocess_ticket')]
  public function preprocessTicket(array &$variables): void {
    $variables['ticket'] = $variables['elements']['#ticket'];
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['attributes'] = $variables['elements']['#attributes'] ?? [];
    $variables['title_attributes'] = new Attribute();
    $variables['content_attributes'] = new Attribute();
    $variables['label'] = $variables['ticket']->label();

    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Invalidates dashboard cache tags when a ticket changes.
   */
  private function invalidateDashboardCache(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() !== 'ticket') {
      return;
    }

    $this->cacheTagsInvalidator->invalidateTags([TicketDashboardService::LIST_CACHE_TAG]);
  }

}
