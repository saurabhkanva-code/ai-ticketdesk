<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ticketdesk\TicketInterface;

/**
 * Provides a delete form for tickets.
 */
class TicketDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    $ticket = $this->getEntity();
    assert($ticket instanceof TicketInterface);

    return (string) $this->t('Are you sure you want to delete ticket %title?', [
      '%title' => $ticket->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ticket = $this->getEntity();
    assert($ticket instanceof TicketInterface);
    $title = $ticket->getTitle();

    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('Deleted ticket %title.', [
      '%title' => $title,
    ]));
    $form_state->setRedirect('entity.ticket.collection');
  }

}
