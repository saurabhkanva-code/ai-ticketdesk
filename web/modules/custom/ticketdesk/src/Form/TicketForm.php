<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\Service\TicketTransitionService;
use Drupal\ticketdesk\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for ticket add and edit forms.
 */
class TicketForm extends ContentEntityForm {

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected readonly TicketTransitionService $transitionService,
    protected readonly EntityTypeManagerInterface $ticketEntityTypeManager,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get(TicketTransitionService::class),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $ticket = $this->entity;
    assert($ticket instanceof TicketInterface);

    $this->restrictDescriptionFormat($form);

    $can_manage = $this->canManageTickets();
    $can_transition = $this->canTransitionTickets();
    $can_assign = $this->canAssignTickets();

    if ($ticket->isNew()) {
      $this->hideFormElement($form, 'status');
      $this->hideFormElement($form, 'assignee');
      $this->hideFormElement($form, 'uid');
      $this->hideFormElement($form, 'created');
      $this->hideFormElement($form, 'changed');
    }
    else {
      $form['ticketdesk_changed'] = [
        '#type' => 'hidden',
        '#value' => $ticket->getChangedTime(),
      ];
      $form['ticketdesk_original_status'] = [
        '#type' => 'hidden',
        '#value' => $ticket->getStatus(),
      ];

      if (!$can_manage) {
        $this->hideFormElement($form, 'priority');
        $this->hideFormElement($form, 'status');
        $this->hideFormElement($form, 'assignee');
        $this->hideFormElement($form, 'uid');
        $this->hideFormElement($form, 'created');
        $this->hideFormElement($form, 'changed');
      }
      else {
        if (!$can_assign) {
          $this->hideFormElement($form, 'assignee');
        }
        if ($can_transition) {
          $this->setStatusWidgetOptions($form, $this->buildStatusOptions($ticket));
        }
        else {
          $this->hideFormElement($form, 'status');
        }
        $this->hideFormElement($form, 'uid');
        $this->hideFormElement($form, 'created');
        $this->hideFormElement($form, 'changed');
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $ticket = $this->entity;
    assert($ticket instanceof TicketInterface);

    if ($ticket->isNew()) {
      return;
    }

    $expected_changed = (int) $form_state->getValue('ticketdesk_changed');
    $unchanged = $this->ticketEntityTypeManager->getStorage('ticket')->loadUnchanged($ticket->id());
    if ($unchanged instanceof TicketInterface) {
      $concurrency_error = $this->transitionService->assertConcurrentSafe($unchanged, $expected_changed);
      if ($concurrency_error !== NULL) {
        $form_state->setErrorByName('ticketdesk_changed', $concurrency_error);
      }
    }

    if (!$this->canTransitionTickets()) {
      return;
    }

    $original_status = (string) $form_state->getValue('ticketdesk_original_status');
    $new_status = $ticket->getStatus();
    if ($new_status !== $original_status) {
      $transition_error = $this->transitionService->validateTransition($original_status, $new_status);
      if ($transition_error !== NULL) {
        $form_state->setErrorByName('status', $transition_error);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity(): void {
    parent::prepareEntity();

    $ticket = $this->entity;
    assert($ticket instanceof TicketInterface);

    if ($ticket->isNew()) {
      $ticket->setOwnerId((int) $this->currentUser()->id());
      $ticket->setStatus(TicketInterface::STATUS_OPEN);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $ticket = $this->entity;
    assert($ticket instanceof TicketInterface);

    $message = $result === SAVED_NEW
      ? $this->t('Created ticket %title.', ['%title' => $ticket->getTitle()])
      : $this->t('Updated ticket %title.', ['%title' => $ticket->getTitle()]);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($ticket->toUrl());

    return $result;
  }

  /**
   * Builds status select options for allowed transitions.
   *
   * @return array<string, string>
   */
  protected function buildStatusOptions(TicketInterface $ticket): array {
    $current = $ticket->getStatus();
    $status_options = Ticket::getStatusOptions();
    $options = [
      $current => $status_options[$current] ?? $current,
    ];

    foreach ($this->transitionService->getAllowedTransitions($current) as $status) {
      $options[$status] = $status_options[$status] ?? $status;
    }

    return $options;
  }

  /**
   * Restricts the description field to plain text.
   */
  protected function restrictDescriptionFormat(array &$form): void {
    if (!isset($form['description']['widget'][0])) {
      return;
    }

    $form['description']['widget'][0]['#allowed_formats'] = ['plain_text'];
    if (isset($form['description']['widget'][0]['format'])) {
      $form['description']['widget'][0]['format']['#access'] = FALSE;
    }
  }

  /**
   * Sets status widget options regardless of widget structure.
   */
  protected function setStatusWidgetOptions(array &$form, array $options): void {
    if (isset($form['status']['widget']['#options'])) {
      $form['status']['widget']['#options'] = $options;
    }
    elseif (isset($form['status']['widget'][0]['#options'])) {
      $form['status']['widget'][0]['#options'] = $options;
    }
    elseif (isset($form['status']['widget'][0]['value']['#options'])) {
      $form['status']['widget'][0]['value']['#options'] = $options;
    }
  }

  /**
   * Hides a base field element on the entity form.
   */
  protected function hideFormElement(array &$form, string $field_name): void {
    if (isset($form[$field_name])) {
      $form[$field_name]['#access'] = FALSE;
    }
  }

  /**
   * Whether the current user can manage tickets as an agent.
   */
  protected function canManageTickets(): bool {
    return $this->currentUser()->hasPermission('administer tickets')
      || $this->currentUser()->hasPermission('edit any ticket');
  }

  /**
   * Whether the current user can transition ticket status.
   */
  protected function canTransitionTickets(): bool {
    return $this->currentUser()->hasPermission('administer tickets')
      || $this->currentUser()->hasPermission('transition ticket');
  }

  /**
   * Whether the current user can assign tickets.
   */
  protected function canAssignTickets(): bool {
    return $this->currentUser()->hasPermission('administer tickets')
      || $this->currentUser()->hasPermission('assign ticket');
  }

}
