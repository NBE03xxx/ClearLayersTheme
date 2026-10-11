<?php

/**
 * @file
 * Checks the account SDC through Canvas forms and Core menu rendering.
 * Run with Drush php:script. Does not save pages, patterns or menu settings.
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Pattern;
use Drupal\canvas\Form\ComponentInstanceForm;
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
$xpath = static function (string $html): DOMXPath {
  $document = new DOMDocument();
  @$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
  return new DOMXPath($document);
};
\Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('clear_layers'));
$component = Component::load('sdc.clear_layers.account-menu');
$check($component !== NULL && $component->status(), 'Account menu SDC must be enabled.');
$source = $component->getComponentSource();
$uuid = \Drupal::service('uuid')->generate();
$renderer = \Drupal::service('renderer');
$switcher = \Drupal::service('account_switcher');
$tested = 0;
$menuBefore = \Drupal::config('system.menu.account')->getRawData();
$admin = User::load(1);
$check($admin !== NULL, 'Administrator account required for authenticated checks.');

foreach ([User::getAnonymousUser(), $admin] as $account) {
  $switcher->switchTo($account);
  try {
    foreach (['', '   ', 'アカウント <script>alert("x")</script> & 設定'] as $title) {
      foreach (['left', 'center', 'right'] as $alignment) {
        foreach ([FALSE, TRUE] as $show) {
          foreach ([FALSE, TRUE] as $showAccount) {
            $props = ['title_override' => $title, 'show_title' => $show, 'title_alignment' => $alignment, 'show_account_link' => $showAccount];
            $pattern = Pattern::create([
              'id' => 'clear_layers_account_menu_test',
              'label' => 'Account menu test',
              'component_tree' => [[
                'uuid' => $uuid,
                'component_id' => $component->id(),
                'component_version' => $component->getActiveVersion(),
                'inputs' => $props,
              ]],
            ]);
            $host = Pattern::createEmptyHostEntity();
            $item = $pattern->getComponentTree()->getComponentTreeItemByUuid($uuid);
            $model = $source->inputToClientModel($source->getExplicitInput($uuid, $item, $host));
            $input = $source->clientModelToInput($uuid, $component, $model, $host);
            $check(count($source->validateComponentInput($input, $uuid, $host)) === 0, 'Canvas rejected account menu inputs.');
            foreach ($props as $key => $value) {
              $check(($input[$key] ?? ($key === 'title_override' ? '' : NULL)) === $value, 'Canvas lost input: ' . $key);
            }
            foreach ([FALSE, TRUE] as $preview) {
              $build = $pattern->getComponentTree()->toRenderable($pattern, $preview);
              $html = (string) $renderer->renderInIsolation($build);
              $document = $xpath($html);
              $check(!str_contains($html, 'data-fallback='), 'Canvas fallback rendered.');
              $nav = $document->query('//nav[contains(@class,"lg-footer-menu-block")]')->item(0);
              $check($nav !== NULL, 'Menu navigation missing.');
              $heading = $document->query('//h2[@id="' . $nav->getAttribute('aria-labelledby') . '"]');
              $expectedTitle = trim($title) !== '' ? trim($title) : \Drupal\system\Entity\Menu::load('account')->label();
              $check($heading->length === 1 && $heading->item(0)->textContent === $expectedTitle, 'Title/fallback or accessible name incorrect.');
              $check($document->query('//script')->length === 0, 'Title was not escaped.');
              $check(str_contains($html, '--footer-menu-title-display: ' . ($show ? 'block' : 'none')), 'Title visibility incorrect.');
              $check(str_contains($html, '--footer-menu-title-align: ' . $alignment), 'Title alignment incorrect.');
              $login = $document->query('//nav//a[contains(@href,"/user/login")]');
              $logout = $document->query('//nav//a[contains(@href,"/user/logout")]');
              $check($login->length === ($account->isAnonymous() ? 1 : 0), 'Login link incorrect or leaked through cache.');
              $check($logout->length === ($account->isAuthenticated() ? 1 : 0), 'Logout link incorrect or leaked through cache.');
              if ($account->isAuthenticated()) {
                parse_str(parse_url($logout->item(0)->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);
                $check(!empty($query['token']), 'Logout CSRF token missing.');
              }
              $expectedLinks = 1 + (int) ($account->isAuthenticated() && $showAccount);
              $check($document->query('//nav//a')->length === $expectedLinks, 'My account visibility incorrect: ' . json_encode([$account->id(), $showAccount, $expectedLinks, $document->query('//nav//a')->length]));
              $tested++;
            }
          }
        }
      }
    }
    if ($account->isAuthenticated()) {
      $request = \Drupal::request();
      $request->request->set('form_canvas_tree', json_encode(['type' => $component->id() . '@' . $component->getActiveVersion()]));
      $request->request->set('form_canvas_selected', $uuid);
      $request->request->set('form_canvas_props', json_encode($model));
      $form = \Drupal::formBuilder()->getForm(ComponentInstanceForm::class, $pattern);
      foreach (array_keys($props) as $key) {
        $field = $form['canvas_component_props'][$uuid][$key] ?? NULL;
        $check($field !== NULL && empty($field['#disabled']), 'Canvas control unavailable: ' . $key);
      }
      $formHtml = (string) $renderer->renderInIsolation($form);
      $check(str_contains($formHtml, 'ユーザーアカウント情報を表示'), 'Account visibility control label is unclear.');
    }
    // Existing footer wrappers must also control titles of non-footer menus.
    $wrapper = Component::load('sdc.clear_layers.footer-menu');
    $block = Component::load('block.system_menu_block.account');
    foreach (['', '接続メニュー <script>bad()</script>'] as $title) {
      $nested = Pattern::create([
        'id' => 'clear_layers_nested_menu_test',
        'label' => 'Nested account menu test',
        'component_tree' => [
          ['uuid' => $uuid, 'component_id' => $wrapper->id(), 'component_version' => $wrapper->getActiveVersion(), 'inputs' => ['title_override' => $title, 'show_title' => TRUE, 'title_alignment' => 'right']],
          ['uuid' => \Drupal::service('uuid')->generate(), 'parent_uuid' => $uuid, 'slot' => 'content', 'component_id' => $block->id(), 'component_version' => $block->getActiveVersion(), 'inputs' => ['label' => 'アカウント', 'label_display' => '0', 'level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]],
        ],
      ]);
      $build = $nested->getComponentTree()->toRenderable($nested);
      $html = (string) $renderer->renderInIsolation($build);
      $document = $xpath($html);
      $heading = $document->query('//nav[contains(@class,"lg-footer-menu-block--canvas")]//h2');
      $check($heading->length === 1 && $heading->item(0)->textContent === ($title !== '' ? $title : 'アカウント'), 'Existing wrapper failed to control account menu title.');
      $check($document->query('//script')->length === 0, 'Nested menu title was not escaped.');
    }
  }
  finally {
    $switcher->switchBack();
  }
}
$check(\Drupal::config('system.menu.account')->getRawData() === $menuBefore, 'Site account menu configuration changed.');
echo "PASS: $tested anonymous/authenticated, preview/published renders; Canvas input/form, title controls, optional account link, cache separation and logout token; existing wrapper/account menu compatibility.\n";
