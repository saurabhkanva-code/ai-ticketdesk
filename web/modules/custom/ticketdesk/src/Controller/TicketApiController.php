<?php

declare(strict_types=1);

namespace Drupal\ticketdesk\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ticketdesk\Service\TicketApiNormalizer;
use Drupal\ticketdesk\Service\TicketApiValidator;
use Drupal\ticketdesk\Service\TicketAccessService;
use Drupal\ticketdesk\Service\TicketTransitionService;
use Drupal\ticketdesk\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * JSON API controller for tickets.
 */
class TicketApiController extends ControllerBase {

  /**
   * Maximum tickets returned by the list endpoint.
   */
  private const LIST_LIMIT = 100;

  protected EntityStorageInterface $ticketStorage;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected readonly TicketApiNormalizer $normalizer,
    protected readonly TicketApiValidator $validator,
    protected readonly TicketTransitionService $transitionService,
    protected readonly AccountProxyInterface $currentUserProxy,
    protected readonly TicketAccessService $ticketAccess,
  ) {
    $this->ticketStorage = $entity_type_manager->getStorage('ticket');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(TicketApiNormalizer::class),
      $container->get(TicketApiValidator::class),
      $container->get(TicketTransitionService::class),
      $container->get('current_user'),
      $container->get(TicketAccessService::class),
    );
  }

  /**
   * Lists tickets visible to the current user.
   */
  public function list(): JsonResponse {
    $account = $this->currentUserProxy;

    if ($account->isAnonymous()) {
      return $this->error('Authentication required.', Response::HTTP_UNAUTHORIZED);
    }

    $query = $this->ticketStorage->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->range(0, self::LIST_LIMIT);

    if (!$this->ticketAccess->canViewAll($account->getAccount())) {
      $this->ticketAccess->applyInvolvementConditions($query, $account->getAccount());
    }

    $ids = $query->execute();
    $tickets = $this->ticketStorage->loadMultiple($ids);

    return $this->success([
      'data' => $this->normalizer->normalizeList(array_values($tickets)),
      'meta' => [
        'count' => count($tickets),
      ],
    ]);
  }

  /**
   * Returns a single ticket.
   */
  public function get(TicketInterface $ticket): JsonResponse {
    if (!$ticket->access('view')) {
      throw new AccessDeniedHttpException('You do not have permission to view this ticket.');
    }

    return $this->success([
      'data' => $this->normalizer->normalize($ticket),
    ]);
  }

  /**
   * Creates a new ticket.
   */
  public function createTicket(Request $request): JsonResponse {
    if (!$this->entityTypeManager()->getAccessControlHandler('ticket')->createAccess(NULL, $this->currentUserProxy)) {
      throw new AccessDeniedHttpException('You do not have permission to create tickets.');
    }

    $payload = $this->decodeJson($request);
    $errors = $this->validator->validateCreate($payload);
    if ($errors !== []) {
      return $this->error($errors[0], Response::HTTP_BAD_REQUEST);
    }

    /** @var \Drupal\ticketdesk\TicketInterface $ticket */
    $ticket = $this->ticketStorage->create([
      'title' => $this->validator->stringValue($payload['title']),
      'description' => [
        'value' => $this->validator->sanitizeDescription($payload['description']),
        'format' => 'plain_text',
      ],
      'priority' => $this->validator->stringValue($payload['priority'] ?? TicketInterface::PRIORITY_MEDIUM),
      'status' => TicketInterface::STATUS_OPEN,
      'uid' => (int) $this->currentUserProxy->id(),
    ]);
    $ticket->save();

    return $this->success([
      'data' => $this->normalizer->normalize($ticket),
    ], Response::HTTP_CREATED);
  }

  /**
   * Updates an existing ticket.
   */
  public function patch(TicketInterface $ticket, Request $request): JsonResponse {
    if (!$ticket->access('update')) {
      throw new AccessDeniedHttpException('You do not have permission to update this ticket.');
    }

    $payload = $this->decodeJson($request);
    $errors = $this->validator->validateUpdate($payload, $ticket, $this->currentUserProxy);
    if ($errors !== []) {
      $status = str_contains($errors[0], 'permission') ? Response::HTTP_FORBIDDEN : Response::HTTP_BAD_REQUEST;
      return $this->error($errors[0], $status);
    }

    $unchanged = $this->ticketStorage->loadUnchanged($ticket->id());
    if (!$unchanged instanceof TicketInterface) {
      throw new NotFoundHttpException('Ticket not found.');
    }

    $expected_changed = (int) $payload['changed'];
    $concurrency_error = $this->transitionService->assertConcurrentSafe($unchanged, $expected_changed);
    if ($concurrency_error !== NULL) {
      return $this->error($concurrency_error, Response::HTTP_CONFLICT);
    }

    $original_status = $unchanged->getStatus();
    $can_manage = $this->ticketAccess->canManageTicketFields($ticket, $this->currentUserProxy->getAccount());

    if (array_key_exists('title', $payload)) {
      $ticket->setTitle($this->validator->stringValue($payload['title']));
    }

    if (array_key_exists('description', $payload)) {
      $ticket->setDescription($this->validator->sanitizeDescription($payload['description']));
    }

    if ($can_manage && array_key_exists('priority', $payload)) {
      $ticket->setPriority($this->validator->stringValue($payload['priority']));
    }

    if (array_key_exists('status', $payload)) {
      $new_status = $this->validator->stringValue($payload['status']);
      if ($new_status !== $original_status) {
        $transition_error = $this->transitionService->validateTransition($original_status, $new_status);
        if ($transition_error !== NULL) {
          return $this->error($transition_error, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $ticket->setStatus($new_status);
      }
    }

    if (array_key_exists('assignee', $payload)) {
      $assignee = $payload['assignee'];
      $ticket->setAssigneeId($assignee === NULL ? NULL : (int) $assignee);
    }

    try {
      $ticket->save();
    }
    catch (\Drupal\Core\Entity\EntityStorageException $e) {
      $message = $e->getMessage();
      if (str_contains($message, 'transition') || str_contains($message, 'status')) {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY);
      }
      if (str_contains($message, 'permission')) {
        return $this->error($message, Response::HTTP_FORBIDDEN);
      }
      throw $e;
    }

    return $this->success([
      'data' => $this->normalizer->normalize($ticket),
    ]);
  }

  /**
   * Decodes a JSON request body.
   *
   * @return array<string, mixed>
   */
  protected function decodeJson(Request $request): array {
    $content = trim($request->getContent());
    if ($content === '') {
      return [];
    }

    try {
      $payload = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid JSON payload.');
    }

    if (!is_array($payload)) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('JSON payload must be an object.');
    }

    return $payload;
  }

  /**
   * Builds a successful JSON response.
   *
   * @param array<string, mixed> $data
   */
  protected function success(array $data, int $status = Response::HTTP_OK): JsonResponse {
    return new JsonResponse($data, $status);
  }

  /**
   * Builds an error JSON response.
   */
  protected function error(string $message, int $status): JsonResponse {
    return new JsonResponse(['message' => $message], $status);
  }

}
