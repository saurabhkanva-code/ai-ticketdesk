<?php

declare(strict_types=1);

namespace Drupal\Tests\ticketdesk\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ticketdesk\Traits\TicketTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for ticketdesk functional tests.
 */
#[RunTestsInSeparateProcesses]
abstract class TicketdeskFunctionalTestBase extends BrowserTestBase {

  use TicketTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ticketdesk', 'options'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Performs an authenticated JSON API request.
   *
   * @param array<string, mixed> $options
   */
  protected function apiRequest(string $method, string $path, array $options = []): ResponseInterface {
    $client = $this->getHttpClient();
    $options['http_errors'] = FALSE;
    $options['cookies'] = $this->getSessionCookies();
    $options['headers'] = ($options['headers'] ?? []) + [
      'Accept' => 'application/json',
    ];

    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], TRUE)) {
      $options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    }

    return $client->request($method, $this->buildUrl($path), $options);
  }

  /**
   * Decodes a JSON API response body.
   *
   * @return array<string, mixed>
   */
  protected function decodeResponse(ResponseInterface $response): array {
    return Json::decode((string) $response->getBody());
  }

}
