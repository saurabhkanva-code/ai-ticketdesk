<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ticketdesk\Form\TicketDeleteForm;
use Drupal\ticketdesk\Form\TicketForm;
use Drupal\ticketdesk\TicketAccessControlHandler;
use Drupal\ticketdesk\TicketHtmlRouteProvider;
use Drupal\ticketdesk\TicketInterface;
use Drupal\ticketdesk\TicketListBuilder;
use Drupal\ticketdesk\TicketViewBuilder;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the ticket entity class.
 */
#[ContentEntityType(
  id: 'ticket',
  label: new TranslatableMarkup('Ticket'),
  label_collection: new TranslatableMarkup('Tickets'),
  label_singular: new TranslatableMarkup('ticket'),
  label_plural: new TranslatableMarkup('tickets'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
    'owner' => 'uid',
  ],
  handlers: [
    'access' => TicketAccessControlHandler::class,
    'list_builder' => TicketListBuilder::class,
    'view_builder' => TicketViewBuilder::class,
    'views_data' => EntityViewsData::class,
    'form' => [
      'default' => TicketForm::class,
      'add' => TicketForm::class,
      'edit' => TicketForm::class,
      'delete' => TicketDeleteForm::class,
    ],
    'route_provider' => ['html' => TicketHtmlRouteProvider::class],
  ],
  links: [
    'canonical' => '/ticketdesk/ticket/{ticket}',
    'add-form' => '/ticketdesk/ticket/add',
    'edit-form' => '/ticketdesk/ticket/{ticket}/edit',
    'delete-form' => '/ticketdesk/ticket/{ticket}/delete',
    'collection' => '/admin/content/tickets',
  ],
  admin_permission: 'administer tickets',
  collection_permission: 'view any ticket',
  base_table: 'ticket',
  label_count: [
    'singular' => '@count ticket',
    'plural' => '@count tickets',
  ],
)]
class Ticket extends ContentEntityBase implements TicketInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setDescription(new TranslatableMarkup('The ticket title.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDescription(new TranslatableMarkup('The ticket description.'))
      ->setRequired(TRUE)
      ->setDefaultValue([
        'value' => '',
        'format' => 'plain_text',
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 5,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Priority'))
      ->setDescription(new TranslatableMarkup('The ticket priority.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', static::getPriorityOptions())
      ->setDefaultValue(self::PRIORITY_MEDIUM)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDescription(new TranslatableMarkup('The ticket status.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', static::getStatusOptions())
      ->setDefaultValue(self::STATUS_OPEN)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['assignee'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Assignee'))
      ->setDescription(new TranslatableMarkup('The agent assigned to this ticket.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 4,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'ticketdesk_assignee',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time the ticket was created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time the ticket was last updated.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Gets all allowed status values.
   *
   * @return array<string, string>
   */
  public static function getStatusOptions(): array {
    return [
      self::STATUS_OPEN => 'Open',
      self::STATUS_IN_PROGRESS => 'In progress',
      self::STATUS_RESOLVED => 'Resolved',
      self::STATUS_CLOSED => 'Closed',
    ];
  }

  /**
   * Gets all allowed priority values.
   *
   * @return array<string, string>
   */
  public static function getPriorityOptions(): array {
    return [
      self::PRIORITY_LOW => 'Low',
      self::PRIORITY_MEDIUM => 'Medium',
      self::PRIORITY_HIGH => 'High',
      self::PRIORITY_CRITICAL => 'Critical',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): static {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->get('description')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): static {
    $this->set('description', [
      'value' => $description,
      'format' => 'plain_text',
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): string {
    return $this->get('priority')->value ?? self::PRIORITY_MEDIUM;
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(string $priority): static {
    $this->set('priority', $priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? self::STATUS_OPEN;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssigneeId(): ?int {
    $value = $this->get('assignee')->target_id;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssigneeId(?int $uid): static {
    $this->set('assignee', $uid);
    return $this;
  }

}
