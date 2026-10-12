<?php

/**
 * @file
 * Validates fresh Canvas definitions without saving generated site content.
 * Optional -- verify-existing checks idempotence on an already configured site.
 */

use Drupal\canvas\Entity\Component;
use Drupal\clear_layers\Setup\CanvasSetup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AnonymousUserSession;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$active = \Drupal::service('config.storage');
$snapshot = static function () use ($active): array {
  $result = [];
  foreach ($active->listAll() as $name) {
    $result[$name] = $active->read($name);
  }
  return $result;
};
$before = $snapshot();
$versions = [];
foreach (CanvasSetup::COMPONENTS as $id) {
  $component = Component::load($id);
  $check($component !== NULL, 'Enable the theme and discover its components before running this test: ' . $id);
  $versions[$id] = $component->getActiveVersion();
}
$validated = [];
foreach (CanvasSetup::definitions($versions) as $type => $definitions) {
  foreach ($definitions as $id => $values) {
    // Use a new ID so validation also covers entities not saved on this site.
    $values['id'] = 'clear_layers_setup_test_' . $id;
    $entity = \Drupal::entityTypeManager()->getStorage($type)->create($values);
    $violations = $entity->getTypedData()->validate();
    foreach ($violations as $violation) {
      print $id . ':' . $violation->getPropertyPath() . ': ' . $violation->getMessage() . PHP_EOL;
    }
    $check(count($violations) === 0, 'Generated configuration failed Canvas validation: ' . $id);
    $validated[] = $id;
  }
}
$check(!CanvasSetup::hasAccess(new AnonymousUserSession()), 'An anonymous visitor must not have setup access.');

// Check the bundled subscriber without enabling it or changing the real theme.
if (!class_exists(\Drupal\clear_layers_canvas_taxonomy\EventSubscriber\TaxonomyPageVariantSubscriber::class, FALSE)) {
  require_once dirname(__DIR__) . '/starter-kit/modules/clear_layers_canvas_taxonomy/src/EventSubscriber/TaxonomyPageVariantSubscriber.php';
}
$memory = new MemoryStorage();
$memory->write('system.theme', ['default' => 'clear_layers']);
$factory = new ConfigFactory($memory, \Drupal::service('event_dispatcher'), \Drupal::service('config.typed'));
$subscriber = new \Drupal\clear_layers_canvas_taxonomy\EventSubscriber\TaxonomyPageVariantSubscriber(\Drupal::entityTypeManager(), $factory);
$route = new RouteMatch('entity.taxonomy_term.canonical', new \Symfony\Component\Routing\Route('/taxonomy/term/{taxonomy_term}'));
$event = new PageDisplayVariantSelectionEvent('block_page', $route);
$subscriber->onSelect($event);
if (\Drupal::entityTypeManager()->getStorage('page_variant')->load('taxonomy_listing')?->status()) {
  $check(($event->getPluginConfiguration()['page_variant'] ?? NULL) === 'taxonomy_listing', 'A term page did not select the dedicated template.');
}
$memory->write('system.theme', ['default' => 'other_theme']);
$factory->reset('system.theme');
$event = new PageDisplayVariantSelectionEvent('block_page', $route);
$subscriber->onSelect($event);
$check($event->getPluginId() === 'block_page', 'The helper must stop selecting Clear Layers templates after switching themes.');
$check(in_array('config:system.theme', $event->getCacheTags(), TRUE), 'Theme switches must invalidate the helper selection.');

$mode = $extra[0] ?? 'validate';
$check(in_array($mode, ['validate', 'verify-existing'], TRUE), 'Use validate or verify-existing.');
if ($mode === 'verify-existing') {
  // This branch is safe only when setup can retain all existing configuration.
  foreach (CanvasSetup::TARGETS as $type => $ids) {
    foreach ($ids as $id) {
      $check(\Drupal::entityTypeManager()->getStorage($type)->load($id) !== NULL, 'verify-existing requires the four targets to exist.');
    }
  }
  foreach (CanvasSetup::COMPONENTS as $id) {
    $check(Component::load($id)->status(), 'verify-existing requires the necessary components to be enabled.');
  }
  $check(\Drupal::moduleHandler()->moduleExists('clear_layers_canvas_taxonomy'), 'verify-existing requires the bundled helper to be enabled.');
  $check(CanvasSetup::blockers() === [], 'Resolve setup prerequisites before checking idempotence.');
  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo(\Drupal\user\Entity\User::load(1));
  try {
    $result = CanvasSetup::create(FALSE);
    $check($result['created'] === [] && count($result['retained']) === 4 && !$result['default_changed'], 'Repeated setup must retain existing content and the site default.');
    $form = \Drupal::formBuilder()->getForm(\Drupal\system\Form\ThemeSettingsForm::class, 'clear_layers');
    $button = $form['clear_layers_canvas_setup']['create'];
    $check($button['#submit'] === ['clear_layers_canvas_setup_submit'], 'The setup button must not run appearance-save handlers.');
    $check($button['#disabled'] === FALSE, 'The setup button is unexpectedly disabled.');
    $state = (new FormState())->setValue('clear_layers_setup_default', TRUE);
    clear_layers_canvas_setup_cleanup($form, $state);
    $check(!$state->hasValue('clear_layers_setup_default'), 'The setup option must not be saved as a theme setting.');
    foreach ($form['clear_layers_canvas_setup'] as $element) {
      if (is_array($element) && isset($element['#url'])) {
        $element['#url']->toString();
      }
    }
  }
  finally {
    $switcher->switchBack();
  }
}
$check($before === $snapshot(), 'Validation or repeated setup changed active configuration.');
print json_encode(['validated_new_definitions' => $validated, 'routing_and_access' => 'passed', 'existing_configuration' => $mode === 'verify-existing' ? 'retained' : 'unchanged'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
