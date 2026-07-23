<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\ticketdesk\Ajax\TicketdeskResetFiltersCommand;
use Drupal\ticketdesk\Ajax\TicketdeskUpdateUrlCommand;
use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\Service\TicketDashboardResultsBuilder;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposed filters for the ticket dashboard (agents and admins).
 */
class TicketDashboardFiltersForm extends FormBase {

  /**
   * Query value used to filter unassigned tickets.
   */
  public const ASSIGNEE_UNASSIGNED = '_unassigned';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TicketDashboardResultsBuilder $resultsBuilder,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(TicketDashboardResultsBuilder::class),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ticketdesk_dashboard_filters';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $filters = $this->resultsBuilder->getActiveFilters();
    $ajax = [
      'callback' => '::ajaxUpdateResults',
      'wrapper' => 'ticketdesk-dashboard-results',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Updating tickets…'),
      ],
    ];

    $form['#attributes']['class'][] = 'ticketdesk-dashboard__filters-form';
    $form['#attached']['library'][] = 'core/drupal.ajax';

    $form['filters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filters'],
      ],
    ];

    $form['filters']['requester'] = [
      '#type' => 'select',
      '#title' => $this->t('Requester'),
      '#title_display' => 'before',
      '#options' => $this->getRequesterOptions(),
      '#default_value' => $filters['requester'],
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filter'],
      ],
    ];

    $form['filters']['assignee'] = [
      '#type' => 'select',
      '#title' => $this->t('Assignee'),
      '#title_display' => 'before',
      '#options' => $this->getAssigneeOptions(),
      '#default_value' => $filters['assignee'],
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filter'],
      ],
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#title_display' => 'before',
      '#options' => $this->getStatusOptions(),
      '#default_value' => $filters['status'],
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filter'],
      ],
    ];

    $form['filters']['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#title_display' => 'before',
      '#options' => $this->getPriorityOptions(),
      '#default_value' => $filters['priority'],
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filter'],
      ],
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['ticketdesk-dashboard__filter-actions'],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply'),
        '#ajax' => $ajax,
        '#attributes' => [
          'class' => ['ticketdesk-dashboard__filter-submit', 'button', 'button--primary'],
        ],
      ],
      'reset' => [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#name' => 'reset',
        '#ajax' => $ajax,
        '#submit' => ['::resetFilters'],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['ticketdesk-dashboard__filter-reset', 'button'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->isAjaxRequest()) {
      return;
    }

    $filters = $this->extractFilters($form_state);
    $query = array_filter(
      $filters,
      static fn (string $value): bool => $value !== '',
    );
    $form_state->setRedirect('ticketdesk.dashboard', [], ['query' => $query]);
  }

  /**
   * Whether the current request is a Drupal AJAX form submission.
   */
  protected function isAjaxRequest(): bool {
    $request = $this->getRequest();
    return $request->isXmlHttpRequest()
      || $request->query->get('_wrapper_format') === 'drupal_ajax';
  }

  /**
   * Clears dashboard filters and rebuilds results.
   */
  public function resetFilters(array &$form, FormStateInterface $form_state): void {
    $form_state->setValue(['filters', 'requester'], '');
    $form_state->setValue(['filters', 'assignee'], '');
    $form_state->setValue(['filters', 'status'], '');
    $form_state->setValue(['filters', 'priority'], '');
  }

  /**
   * AJAX callback that replaces the ticket results table.
   */
  public function ajaxUpdateResults(array &$form, FormStateInterface $form_state): AjaxResponse {
    $filters = $this->extractFilters($form_state);
    $results = $this->resultsBuilder->buildResults($filters);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#ticketdesk-dashboard-results',
      $this->renderer->renderRoot($results),
    ));
    $response->addCommand(new TicketdeskUpdateUrlCommand($filters));

    $trigger = $form_state->getTriggeringElement();
    if (($trigger['#name'] ?? '') === 'reset') {
      $response->addCommand(new TicketdeskResetFiltersCommand());
    }

    return $response;
  }

  /**
   * Extracts filter values from form state.
   *
   * @return array<string, string>
   */
  protected function extractFilters(FormStateInterface $form_state): array {
    $values = $form_state->getValue('filters');
    if (!is_array($values)) {
      $values = $form_state->getValues();
    }

    return $this->resultsBuilder->normalizeFilters([
      'requester' => (string) ($values['requester'] ?? ''),
      'assignee' => (string) ($values['assignee'] ?? ''),
      'status' => (string) ($values['status'] ?? ''),
      'priority' => (string) ($values['priority'] ?? ''),
    ]);
  }

  /**
   * Builds requester filter options from ticket owners.
   *
   * @return array<string, string>
   */
  protected function getRequesterOptions(): array {
    $options = ['' => $this->t('- Any -')];
    foreach ($this->loadTicketUserOptions('uid') as $uid => $label) {
      $options[(string) $uid] = $label;
    }
    return $options;
  }

  /**
   * Builds assignee filter options from ticket assignees.
   *
   * @return array<string, string>
   */
  protected function getAssigneeOptions(): array {
    $options = [
      '' => $this->t('- Any -'),
      self::ASSIGNEE_UNASSIGNED => $this->t('Unassigned'),
    ];
    foreach ($this->loadTicketUserOptions('assignee') as $uid => $label) {
      $options[(string) $uid] = $label;
    }
    return $options;
  }

  /**
   * Loads distinct user labels for a ticket reference field.
   *
   * @return array<int, string>
   */
  protected function loadTicketUserOptions(string $field): array {
    $storage = $this->entityTypeManager->getStorage('ticket');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->exists($field)
      ->sort($field)
      ->execute();

    if ($ids === []) {
      return [];
    }

    $user_ids = [];
    foreach ($storage->loadMultiple($ids) as $ticket) {
      $uid = $field === 'assignee'
        ? $ticket->getAssigneeId()
        : (int) $ticket->getOwnerId();
      if ($uid) {
        $user_ids[$uid] = $uid;
      }
    }

    if ($user_ids === []) {
      return [];
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($user_ids);
    $options = [];
    foreach ($users as $user) {
      if ($user instanceof UserInterface) {
        $options[(int) $user->id()] = $user->getDisplayName();
      }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * @return array<string, string>
   */
  protected function getStatusOptions(): array {
    return ['' => $this->t('- Any -')] + Ticket::getStatusOptions();
  }

  /**
   * @return array<string, string>
   */
  protected function getPriorityOptions(): array {
    return ['' => $this->t('- Any -')] + Ticket::getPriorityOptions();
  }

}
