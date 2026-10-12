<?php

/**
 * @file
 * Exercises the actual menu block form and Canvas defaults through their APIs.
 */

use Drupal\canvas\Entity\Component;
use Drupal\Core\Form\FormState;
use Drupal\system\Plugin\Block\SystemMenuBlock;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$check(\Drupal::moduleHandler()->moduleExists('clear_layers_canvas_taxonomy'), 'Enable the bundled helper first.');
$check(\Drupal::config('system.theme')->get('default') === 'clear_layers', 'Run this check with Clear Layers as the default theme.');
$manager = \Drupal::service('plugin.manager.block');
$footer = $manager->createInstance('system_menu_block:footer');
$check($footer->getConfiguration()['level'] === 1 && $footer->getConfiguration()['depth'] === 1, 'A newly placed footer menu must start with one visible level.');
$form = $footer->blockForm([], new FormState());
$check($form['menu_levels']['depth']['#default_value'] === 1, 'The menu form did not show the one-level default.');
$state = (new FormState())->setValues(['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE, 'add_active_trail_class' => FALSE]);
$footer->blockValidate($form, $state);
$check(!$state->hasAnyErrors(), 'The one-level footer menu failed the real Drupal block form validation.');
$footer->blockSubmit($form, $state);
$check($footer->getConfiguration()['depth'] === 1, 'Submitting the menu form changed the selected level.');
$component = Component::load('block.system_menu_block.footer');
$check($component->getComponentSource()->getDefaultExplicitInput()['depth'] === 1, 'Canvas new-placement inputs did not use the footer default.');
$check($component->get('settings')['default_settings']['depth'] === 1, 'The stored defaults used when adding a Library component are stale; rebuild caches.');
foreach ([NULL, 2] as $savedDepth) {
  $saved = $manager->createInstance('system_menu_block:footer', ['level' => 1, 'depth' => $savedDepth, 'expand_all_items' => FALSE]);
  $check($saved->getConfiguration()['depth'] === $savedDepth, 'An explicit saved footer setting was overwritten.');
}
$main = $manager->createInstance('system_menu_block:main');
$check($manager->getDefinition('system_menu_block:main')['class'] === SystemMenuBlock::class, 'The main menu plugin implementation was changed.');
$check($main->defaultConfiguration()['depth'] === NULL, 'The main/header menu unlimited default was changed.');
print json_encode(['new_footer_form' => 'one level; valid', 'canvas_inputs' => 'one level', 'saved_footer_settings' => 'retained', 'main_menu' => 'unchanged'], JSON_PRETTY_PRINT) . PHP_EOL;
