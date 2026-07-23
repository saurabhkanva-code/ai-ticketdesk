<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ticketdesk\Form\TicketDashboardFiltersForm;
use Drupal\ticketdesk\Service\TicketDashboardResultsBuilder;
use Drupal\ticketdesk\Service\TicketDashboardService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the ticket desk dashboard.
 */
class TicketDashboardController extends ControllerBase {

  public function __construct(
    protected readonly TicketDashboardService $dashboardService,
    protected readonly TicketDashboardResultsBuilder $resultsBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(TicketDashboardService::class),
      $container->get(TicketDashboardResultsBuilder::class),
    );
  }

  /**
   * Displays a sortable ticket table for the dashboard.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function view(): array {
    $view_all = $this->resultsBuilder->canViewAllTickets();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ticketdesk-dashboard'],
      ],
      '#attached' => [
        'library' => ['ticketdesk/dashboard'],
      ],
      '#cache' => [
        'tags' => $this->dashboardService->getCacheTags(),
        'contexts' => [
          'user',
          'url.query_args:sort',
          'url.query_args:order',
          'url.query_args:requester',
          'url.query_args:assignee',
          'url.query_args:status',
          'url.query_args:priority',
        ],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    if ($this->canCreateTicket()) {
      $build['toolbar'] = [
        '#type' => 'container',
        '#weight' => -30,
        '#attributes' => [
          'class' => ['ticketdesk-dashboard__toolbar'],
        ],
        'create' => [
          '#type' => 'link',
          '#title' => $this->t('Create ticket'),
          '#url' => Url::fromRoute('entity.ticket.add_form'),
          '#attributes' => [
            'class' => ['ticketdesk-dashboard__create-link', 'button', 'button--primary'],
          ],
        ],
      ];
    }

    if ($view_all) {
      $build['filters'] = $this->formBuilder()->getForm(TicketDashboardFiltersForm::class);
      $build['filters']['#weight'] = -20;
    }

    $build['results'] = $this->resultsBuilder->buildResults();
    $build['results']['#weight'] = 0;

    return $build;
  }

  /**
   * Whether the current user can create tickets.
   */
  protected function canCreateTicket(): bool {
    return $this->entityTypeManager()
      ->getAccessControlHandler('ticket')
      ->createAccess(NULL, $this->currentUser());
  }

}
