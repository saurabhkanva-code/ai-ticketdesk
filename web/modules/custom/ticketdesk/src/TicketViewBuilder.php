<?php

declare(strict_types=1);

namespace Drupal\ticketdesk;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for ticket entities.
 */
class TicketViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode): array {
    $build = parent::getBuildDefaults($entity, $view_mode);
    $build['#theme'] = 'ticket';
    $build['#ticket'] = $entity;
    return $build;
  }

}
