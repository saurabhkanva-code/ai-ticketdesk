<?php

declare(strict_types=1);

namespace Drupal\ticketdesk;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of ticket entities.
 */
class TicketListBuilder extends EntityListBuilder {

  /**
   * Constructs a TicketListBuilder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Title');
    $header['priority'] = $this->t('Priority');
    $header['status'] = $this->t('Status');
    $header['assignee'] = $this->t('Assignee');
    $header['owner'] = $this->t('Requester');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof TicketInterface);

    $assignee = $entity->get('assignee')->entity;
    $owner = $entity->getOwner();

    $row['title'] = $entity->toLink($entity->getTitle());
    $row['priority'] = TicketInterface::getPriorityOptions()[$entity->getPriority()] ?? $entity->getPriority();
    $row['status'] = TicketInterface::getStatusOptions()[$entity->getStatus()] ?? $entity->getStatus();
    $row['assignee'] = $assignee ? $assignee->getDisplayName() : $this->t('Unassigned');
    $row['owner'] = $owner ? $owner->getDisplayName() : $this->t('Unknown');
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime());

    return $row + parent::buildRow($entity);
  }

}
