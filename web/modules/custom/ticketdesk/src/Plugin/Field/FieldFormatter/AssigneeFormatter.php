<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'ticketdesk_assignee' formatter.
 */
#[FieldFormatter(
  id: 'ticketdesk_assignee',
  label: new TranslatableMarkup('Ticket assignee'),
  description: new TranslatableMarkup('Display the assignee or "Unassigned".'),
  field_types: ['entity_reference'],
)]
class AssigneeFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getTargetEntityTypeId() === 'ticket'
      && $field_definition->getName() === 'assignee';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty() || $items->referencedEntities() === []) {
      return [
        [
          '#plain_text' => (string) $this->t('Unassigned'),
        ],
      ];
    }

    return parent::viewElements($items, $langcode);
  }

}
