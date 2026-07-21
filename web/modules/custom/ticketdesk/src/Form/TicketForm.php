<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for ticket add and edit forms.
 *
 * Business logic for field visibility and status transitions is added in Phase 3.
 */
class TicketForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $ticket = $this->getEntity();
    assert($ticket instanceof \Drupal\ticketdesk\TicketInterface);

    if ($ticket->getOwnerId() === NULL) {
      $ticket->setOwnerId((int) $this->currentUser()->id());
      $ticket->save();
    }

    $message = $result === SAVED_NEW
      ? $this->t('Created ticket %title.', ['%title' => $ticket->getTitle()])
      : $this->t('Updated ticket %title.', ['%title' => $ticket->getTitle()]);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($ticket->toUrl());

    return $result;
  }

}
