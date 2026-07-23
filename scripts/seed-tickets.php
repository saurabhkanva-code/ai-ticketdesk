<?php

/**
 * @file
 * Seeds sample users and tickets for local development and demos.
 *
 * Usage:
 *   ddev drush php:script scripts/seed-tickets.php
 *   ddev drush php:script scripts/seed-tickets.php -- --reset
 */

declare(strict_types=1);

use Drupal\ticketdesk\Entity\Ticket;
use Drupal\ticketdesk\TicketInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

if (!defined('DRUPAL_ROOT')) {
  fwrite(STDERR, "Run this script with Drush: ddev drush php:script scripts/seed-tickets.php\n");
  exit(1);
}

$password = 'ticketdesk';
$reset = in_array('--reset', $extra ?? [], TRUE);

/**
 * Creates or loads a user with a ticketdesk role.
 */
$load_or_create_user = static function (string $name, string $mail, string $role_id) use ($password): User {
  $existing = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name]);
  $user = $existing ? reset($existing) : NULL;
  if ($user instanceof User) {
    if (!$user->hasRole($role_id)) {
      $user->addRole($role_id);
      $user->save();
    }
    return $user;
  }

  $user = User::create([
    'name' => $name,
    'mail' => $mail,
    'pass' => $password,
    'status' => 1,
    'roles' => [$role_id],
  ]);
  $user->save();
  return $user;
};

if (!\Drupal::moduleHandler()->moduleExists('ticketdesk')) {
  throw new \RuntimeException('The ticketdesk module is not enabled. Run: ddev drush en ticketdesk -y');
}

foreach (['ticketdesk_requester', 'ticketdesk_agent', 'ticketdesk_admin'] as $role_id) {
  if (!Role::load($role_id)) {
    throw new \RuntimeException("Missing role {$role_id}. Enable the ticketdesk module first.");
  }
}

$requester = $load_or_create_user('requester1', 'requester1@example.com', 'ticketdesk_requester');
$agent = $load_or_create_user('agent1', 'agent1@example.com', 'ticketdesk_agent');
$admin = $load_or_create_user('admin1', 'admin1@example.com', 'ticketdesk_admin');

// Run saves as admin so presave hooks see appropriate permissions.
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo($admin);

$samples = [
  [
    'title' => 'Laptop will not boot',
    'description' => 'Device powers on but stops at the manufacturer logo.',
    'priority' => TicketInterface::PRIORITY_HIGH,
    'status' => TicketInterface::STATUS_OPEN,
    'uid' => $requester->id(),
    'assignee' => NULL,
  ],
  [
    'title' => 'VPN disconnects hourly',
    'description' => 'Remote VPN drops every hour and must be reconnected manually.',
    'priority' => TicketInterface::PRIORITY_MEDIUM,
    'status' => TicketInterface::STATUS_IN_PROGRESS,
    'uid' => $requester->id(),
    'assignee' => $agent->id(),
  ],
  [
    'title' => 'Shared printer offline',
    'description' => 'Floor 3 printer shows offline in the queue.',
    'priority' => TicketInterface::PRIORITY_LOW,
    'status' => TicketInterface::STATUS_RESOLVED,
    'uid' => $requester->id(),
    'assignee' => $agent->id(),
  ],
  [
    'title' => 'Email alias request',
    'description' => 'Need team@example.com forwarded to the support group.',
    'priority' => TicketInterface::PRIORITY_MEDIUM,
    'status' => TicketInterface::STATUS_CLOSED,
    'uid' => $requester->id(),
    'assignee' => $agent->id(),
  ],
  [
    'title' => 'Critical database alert',
    'description' => 'Production replica lag exceeded the alert threshold.',
    'priority' => TicketInterface::PRIORITY_CRITICAL,
    'status' => TicketInterface::STATUS_IN_PROGRESS,
    'uid' => $admin->id(),
    'assignee' => $agent->id(),
  ],
];

$sample_titles = array_column($samples, 'title');
$storage = \Drupal::entityTypeManager()->getStorage('ticket');

if ($reset) {
  $existing_ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', $sample_titles, 'IN')
    ->execute();
  if ($existing_ids !== []) {
    $existing = $storage->loadMultiple($existing_ids);
    $storage->delete($existing);
    print 'Removed ' . count($existing_ids) . " existing seed ticket(s).\n";
  }
}

$created = 0;
$skipped = 0;
$errors = [];

foreach ($samples as $sample) {
  $duplicate = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', $sample['title'])
    ->range(0, 1)
    ->execute();
  if ($duplicate !== []) {
    $skipped++;
    continue;
  }

  $values = [
    'title' => $sample['title'],
    'description' => [
      'value' => $sample['description'],
      'format' => 'plain_text',
    ],
    'priority' => $sample['priority'],
    'status' => $sample['status'],
    'uid' => $sample['uid'],
  ];
  if ($sample['assignee'] !== NULL) {
    $values['assignee'] = ['target_id' => $sample['assignee']];
  }

  try {
    /** @var \Drupal\ticketdesk\TicketInterface $ticket */
    $ticket = Ticket::create($values);
    $ticket->save();
    $created++;
  }
  catch (\Throwable $e) {
    $errors[] = $sample['title'] . ': ' . $e->getMessage();
  }
}

$account_switcher->switchBack();

\Drupal::service('cache_tags.invalidator')->invalidateTags(['ticketdesk:ticket_list']);

$seed_count = (int) $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', $sample_titles, 'IN')
  ->count()
  ->execute();

print "Seed complete.\n";
print "Created {$created} ticket(s).";
if ($skipped > 0) {
  print " Skipped {$skipped} existing ticket(s).";
}
print "\n";
print "Sample tickets in database: {$seed_count}/" . count($samples) . ".\n";

if ($created === 0 && $skipped > 0 && !$reset) {
  print "To replace sample tickets, run:\n";
  print "  ddev drush php:script scripts/seed-tickets.php -- --reset\n";
}

if ($errors !== []) {
  fwrite(STDERR, "Errors while creating tickets:\n");
  foreach ($errors as $error) {
    fwrite(STDERR, "  - {$error}\n");
  }
  exit(1);
}

print "Users (password for new accounts: {$password}):\n";
print "  - requester1 (ticketdesk_requester)\n";
print "  - agent1 (ticketdesk_agent)\n";
print "  - admin1 (ticketdesk_admin)\n";
print "Front page: /ticketdesk/dashboard\n";
