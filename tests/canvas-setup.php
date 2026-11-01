<?php

/**
 * @file
 * Validates fresh Canvas definitions without saving generated site content.
 * Optional -- verify-existing checks idempotence on an already configured site.
 */

use Drupal\canvas\Entity\Component;
use Drupal\clear_layers\Setup\CanvasSetup;
use Drupal\Core\Form\FormState;
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
$moduleList = \Drupal::service('extension.list.module');
$moduleList->reset();
foreach (CanvasSetup::HELPER_MODULES as $module => $label) {
  $check(isset($moduleList->getList()[$module]), 'The bundled helper must be discoverable: ' . $label);
}
$versions = [];
foreach (CanvasSetup::COMPONENTS as $id) {
  $component = Component::load($id);
  $check($component !== NULL, 'Enable the theme and discover its components before running this test: ' . $id);
  $versions[$id] = $component->getActiveVersion();
}
$validated = [];
$definitionsByType = CanvasSetup::definitions($versions);
$check(array_keys($definitionsByType) === ['page_variant'], 'Setup must not create separate shared patterns.');
$check(array_keys($definitionsByType['page_variant']) === ['site_layout'], 'Normal pages must share one outer template.');
$tree = $definitionsByType['page_variant']['site_layout']['component_tree'];
$ids = array_column($tree, 'component_id');
$check(count(array_keys($ids, 'marker.page_content', TRUE)) === 1, 'There must be exactly one page content marker.');
$check(array_search('sdc.clear_layers.site-header', $ids, TRUE) < array_search('marker.page_content', $ids, TRUE)
  && array_search('marker.page_content', $ids, TRUE) < array_search('sdc.clear_layers.site-footer', $ids, TRUE), 'The editable header and footer must surround page content.');
$check(!in_array('sdc.clear_layers.shared-site-section', $ids, TRUE), 'The page template must own editable components.');
foreach ($definitionsByType as $type => $definitions) {
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

$mode = $extra[0] ?? 'validate';
$check(in_array($mode, ['validate', 'verify-existing'], TRUE), 'Use validate or verify-existing.');
if ($mode === 'verify-existing') {
  // This branch is safe only when setup can retain all existing configuration.
  foreach (CanvasSetup::TARGETS as $type => $ids) {
    foreach ($ids as $id) {
      $check(\Drupal::entityTypeManager()->getStorage($type)->load($id) !== NULL, 'verify-existing requires the common layout to exist.');
    }
  }
  foreach (CanvasSetup::COMPONENTS as $id) {
    $check(Component::load($id)->status(), 'verify-existing requires the necessary components to be enabled.');
  }
  foreach (CanvasSetup::HELPER_MODULES as $module => $label) {
    $check(\Drupal::moduleHandler()->moduleExists($module), 'verify-existing requires the bundled helper to be enabled: ' . $label);
  }
  $check(CanvasSetup::blockers() === [], 'Resolve setup prerequisites before checking idempotence.');
  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo(\Drupal\user\Entity\User::load(1));
  try {
    $result = CanvasSetup::create(FALSE);
    $check($result['created'] === [] && $result['retained'] === ['site_layout'] && !$result['default_changed'], 'Repeated setup must retain existing content and the site default.');
    $form = \Drupal::formBuilder()->getForm(\Drupal\system\Form\ThemeSettingsForm::class, 'clear_layers');
    $button = $form['clear_layers_canvas_setup']['create'];
    $check(in_array('clear_layers_canvas_setup_submit', $button['#submit'], TRUE), 'The setup button must run the Canvas setup handler.');
    // Other modules may append submit observers, such as ECA Form's listener.
    foreach (['::submitForm', 'clear_layers_glass_submit', 'clear_layers_spacing_submit', 'clear_layers_background_submit'] as $handler) {
      $check(!in_array($handler, $button['#submit'], TRUE), 'The setup button must not run appearance-save handlers: ' . $handler);
    }
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
print json_encode(['validated_new_definitions' => $validated, 'layout_and_access' => 'passed', 'existing_configuration' => $mode === 'verify-existing' ? 'retained' : 'unchanged'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
