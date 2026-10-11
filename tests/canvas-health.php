<?php

/**
 * @file
 * Runs Canvas data diagnostics through its API without modifying site config.
 */

use Drupal\canvas\Health\Doctor;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$counts = [];
$invalid = [];
foreach (\Drupal::service(Doctor::class)->runDataChecks(cache: FALSE) as $item) {
  $counts[$item['check']] = ($counts[$item['check']] ?? 0) + 1;
  if (array_filter($item['findings'], static fn (array $finding): bool => $finding['violations'] !== [])) {
    $invalid[] = $item;
  }
}
echo json_encode(['counts' => $counts, 'invalid' => $invalid], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($invalid) {
  throw new RuntimeException('Canvas data validation failed.');
}
