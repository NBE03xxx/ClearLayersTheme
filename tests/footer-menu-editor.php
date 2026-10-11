<?php

/**
 * @file
 * Checks selectable parent markup in Canvas without saving any layouts.
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Pattern;
use Drupal\user\Entity\User;

if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit;
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
\Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('clear_layers'));
$wrapper = Component::load('sdc.clear_layers.footer-menu');
$block = Component::load('block.system_menu_block.footer');
$check($wrapper !== NULL && $block !== NULL, 'Footer components must exist in Canvas.');
$uuid = \Drupal::service('uuid')->generate();
$child = \Drupal::service('uuid')->generate();
$renderer = \Drupal::service('renderer');
$switcher = \Drupal::service('account_switcher');
$tested = 0;
foreach ([User::getAnonymousUser(), User::load(1)] as $account) {
  $switcher->switchTo($account);
  try {
    foreach ([FALSE, TRUE] as $populated) {
      foreach ([FALSE, TRUE] as $showTitle) {
        $rows = [[
          'uuid' => $uuid,
          'component_id' => $wrapper->id(),
          'component_version' => $wrapper->getActiveVersion(),
          'inputs' => ['title_override' => 'メニュー', 'show_title' => $showTitle, 'title_alignment' => 'left'],
        ]];
        if ($populated) {
          $rows[] = [
            'uuid' => $child,
            'parent_uuid' => $uuid,
            'slot' => 'content',
            'component_id' => $block->id(),
            'component_version' => $block->getActiveVersion(),
            'inputs' => ['label' => 'フッター', 'label_display' => '0', 'level' => 1, 'depth' => NULL, 'expand_all_items' => FALSE],
          ];
        }
        $pattern = Pattern::create(['id' => 'clear_layers_footer_editor_test', 'label' => 'Footer editor test', 'component_tree' => $rows]);
        $publicLinks = NULL;
        foreach ([FALSE, TRUE] as $preview) {
          $build = $pattern->getComponentTree()->toRenderable($pattern, $preview);
          $html = (string) $renderer->renderInIsolation($build);
          $check(!str_contains($html, 'data-fallback='), 'Canvas fallback rendered.');
          $document = new DOMDocument();
          @$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
          $xpath = new DOMXPath($document);
          $labels = $xpath->query('//div[contains(@class,"c-footer-menu")]/div[@class="c-footer-menu__editor-label"]');
          $check($labels->length === (int) $preview, 'Editor label missing, nested inside menu, or leaked to public output.');
          $check(str_contains($html, 'c-footer-menu--canvas-preview') === $preview, 'Editor spacing leaked to public output.');
          $links = array_map(static fn (DOMElement $link): string => $link->getAttribute('href') . '|' . $link->textContent, iterator_to_array($xpath->query('//nav//a')));
          if (!$preview) {
            $publicLinks = $links;
          }
          else {
            $check($links === $publicLinks, 'Preview changed menu links.');
            $parentStart = strpos($html, '<!-- canvas-start-' . $uuid . ' -->');
            $label = strpos($html, 'class="c-footer-menu__editor-label"');
            $slotStart = strpos($html, '<!-- canvas-slot-start-' . $uuid . '/content -->');
            $check($parentStart !== FALSE && $label !== FALSE && $slotStart !== FALSE && $parentStart < $label && $label < $slotStart, 'Selection label is covered by the nested slot overlay.');
            if ($populated) {
              $childStart = strpos($html, '<!-- canvas-start-' . $child . ' -->');
              $check($childStart !== FALSE && $slotStart < $childStart, 'Nested menu selection boundary lost.');
            }
          }
          $tested++;
        }
      }
    }
  }
  finally {
    $switcher->switchBack();
  }
}
echo "PASS: $tested empty/populated, title visible/hidden, anonymous/authenticated, preview/public renders; parent target outside slot boundary; child boundaries and links retained.\n";
