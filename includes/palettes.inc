<?php

/**
 * @file
 * Site-wide Clear Layers palette definitions, shared by settings and rendering.
 */

/**
 * Returns coordinated light and dark colors for the administrator's presets.
 */
function clear_layers_color_palettes(): array {
  return [
    'ocean' => [
      'label' => '青 × シアン（オーシャンブルー）',
      'light' => ['paper' => '#edf6ff', 'ink' => '#122b49', 'muted' => '#405671', 'line' => '#9eb6d3', 'accent' => '#0753b5', 'footer' => '#dfedff', 'backdrop' => '#dceaff', 'veil' => 'rgba(225, 239, 255, 0.65)', 'highlight' => '#58c8ff'],
      'dark' => ['paper' => '#0c2039', 'ink' => '#edf6ff', 'muted' => '#abc3df', 'line' => '#4c708f', 'accent' => '#8bd5ff', 'footer' => '#102b49', 'backdrop' => '#06152c', 'veil' => 'rgba(5, 18, 44, 0.4)', 'highlight' => '#35bcff'],
    ],
    'arctic' => [
      'label' => '淡い青 × アクア（アイスブルー）',
      'light' => ['paper' => '#f1fbff', 'ink' => '#143147', 'muted' => '#425e70', 'line' => '#a0bccd', 'accent' => '#00618b', 'footer' => '#e3f6ff', 'backdrop' => '#e3f5fd', 'veil' => 'rgba(230, 247, 255, 0.65)', 'highlight' => '#88e5fa'],
      'dark' => ['paper' => '#0d2635', 'ink' => '#ecfbff', 'muted' => '#a8cad6', 'line' => '#496f80', 'accent' => '#8bddf2', 'footer' => '#123746', 'backdrop' => '#061a29', 'veil' => 'rgba(6, 26, 41, 0.4)', 'highlight' => '#88e5fa'],
    ],
    'indigo' => [
      'label' => '青・藍 × ラベンダー（インディゴ）',
      'light' => ['paper' => '#f3f2ff', 'ink' => '#24274d', 'muted' => '#555674', 'line' => '#a8acd4', 'accent' => '#4b4ab5', 'footer' => '#e9e8ff', 'backdrop' => '#e6e7fb', 'veil' => 'rgba(238, 235, 255, 0.65)', 'highlight' => '#adb0ff'],
      'dark' => ['paper' => '#191b3a', 'ink' => '#f2f1ff', 'muted' => '#c1bfe3', 'line' => '#686895', 'accent' => '#bfbaff', 'footer' => '#27274f', 'backdrop' => '#0c112c', 'veil' => 'rgba(12, 17, 44, 0.4)', 'highlight' => '#b0abff'],
    ],
    'lagoon' => [
      'label' => '青緑 × ミント（ブルーティール）',
      'light' => ['paper' => '#edfafa', 'ink' => '#123936', 'muted' => '#3c5b58', 'line' => '#9abfb9', 'accent' => '#006762', 'footer' => '#dbf1ed', 'backdrop' => '#dcefea', 'veil' => 'rgba(224, 248, 239, 0.65)', 'highlight' => '#65d8d1'],
      'dark' => ['paper' => '#0b282d', 'ink' => '#e8fcf9', 'muted' => '#a2ccc6', 'line' => '#497e7a', 'accent' => '#80e0d2', 'footer' => '#12383c', 'backdrop' => '#061c26', 'veil' => 'rgba(6, 28, 38, 0.4)', 'highlight' => '#55d3c2'],
    ],
    'green_mint' => [
      'label' => '緑 × ミント',
      'light' => ['paper' => '#effbf4', 'ink' => '#15392b', 'muted' => '#476653', 'line' => '#9cbdac', 'accent' => '#146342', 'footer' => '#def1e5', 'backdrop' => '#deefe4', 'veil' => 'rgba(224, 248, 234, 0.65)', 'highlight' => '#72d6a0'],
      'dark' => ['paper' => '#102b20', 'ink' => '#edfcf2', 'muted' => '#aecdb9', 'line' => '#507c62', 'accent' => '#91e0b2', 'footer' => '#163a2b', 'backdrop' => '#071d13', 'veil' => 'rgba(7, 29, 19, 0.4)', 'highlight' => '#58d596'],
    ],
    'green_teal' => [
      'label' => '深緑 × 青緑',
      'light' => ['paper' => '#eef9f6', 'ink' => '#163b34', 'muted' => '#46665d', 'line' => '#9ebcb0', 'accent' => '#176456', 'footer' => '#dfefea', 'backdrop' => '#dcebe2', 'veil' => 'rgba(224, 242, 233, 0.65)', 'highlight' => '#69cbb3'],
      'dark' => ['paper' => '#122d26', 'ink' => '#effaf3', 'muted' => '#b5cdbf', 'line' => '#50776a', 'accent' => '#8cdcc5', 'footer' => '#1a3b30', 'backdrop' => '#091f16', 'veil' => 'rgba(9, 31, 22, 0.4)', 'highlight' => '#58c9ab'],
    ],
    'red_rose' => [
      'label' => '赤 × ローズ',
      'light' => ['paper' => '#fff2f5', 'ink' => '#471e2a', 'muted' => '#76505d', 'line' => '#d0a7b3', 'accent' => '#a52c50', 'footer' => '#fbe3eb', 'backdrop' => '#f5dfe7', 'veil' => 'rgba(255, 232, 241, 0.65)', 'highlight' => '#f38ba9'],
      'dark' => ['paper' => '#351a25', 'ink' => '#fff0f5', 'muted' => '#dcb6c5', 'line' => '#8e5b71', 'accent' => '#ffabc6', 'footer' => '#462131', 'backdrop' => '#200b14', 'veil' => 'rgba(32, 11, 20, 0.4)', 'highlight' => '#f582ab'],
    ],
    'red_coral' => [
      'label' => '赤 × コーラル',
      'light' => ['paper' => '#fff4f1', 'ink' => '#48271f', 'muted' => '#76584e', 'line' => '#d2afa3', 'accent' => '#ad3b2c', 'footer' => '#fbe6de', 'backdrop' => '#f5e0d8', 'veil' => 'rgba(255, 237, 229, 0.65)', 'highlight' => '#f59a80'],
      'dark' => ['paper' => '#351f1b', 'ink' => '#fff3ed', 'muted' => '#dec0b1', 'line' => '#8a6456', 'accent' => '#ffb29a', 'footer' => '#442a24', 'backdrop' => '#210f0c', 'veil' => 'rgba(33, 15, 12, 0.4)', 'highlight' => '#f58b70'],
    ],
    'amber_gold' => [
      'label' => '琥珀色 × ゴールド',
      'light' => ['paper' => '#fff9ed', 'ink' => '#49351b', 'muted' => '#725e40', 'line' => '#ceb98d', 'accent' => '#865300', 'footer' => '#f7edce', 'backdrop' => '#f2e7cb', 'veil' => 'rgba(255, 246, 220, 0.65)', 'highlight' => '#ecc567'],
      'dark' => ['paper' => '#302618', 'ink' => '#fff6df', 'muted' => '#d7c5a0', 'line' => '#877149', 'accent' => '#f0cb7a', 'footer' => '#40321e', 'backdrop' => '#1e1609', 'veil' => 'rgba(30, 22, 9, 0.4)', 'highlight' => '#eabd52'],
    ],
    'amber_orange' => [
      'label' => '琥珀色 × オレンジ',
      'light' => ['paper' => '#fff5ec', 'ink' => '#4d2d19', 'muted' => '#78583e', 'line' => '#d0af8c', 'accent' => '#a14b0b', 'footer' => '#f9e6d3', 'backdrop' => '#f3dfc8', 'veil' => 'rgba(255, 240, 221, 0.65)', 'highlight' => '#f1aa64'],
      'dark' => ['paper' => '#342319', 'ink' => '#fff2e4', 'muted' => '#dfbfa1', 'line' => '#916b49', 'accent' => '#ffbd82', 'footer' => '#462d1d', 'backdrop' => '#211307', 'veil' => 'rgba(33, 19, 7, 0.4)', 'highlight' => '#f3a45b'],
    ],
    'monochrome_silver' => [
      'label' => 'モノクロ × シルバー',
      'light' => ['paper' => '#f7f7f7', 'ink' => '#262626', 'muted' => '#5b5b5b', 'line' => '#b0b0b0', 'accent' => '#454545', 'footer' => '#ededed', 'backdrop' => '#e5e5e5', 'veil' => 'rgba(240, 240, 240, 0.65)', 'highlight' => '#c8c8c8'],
      'dark' => ['paper' => '#242424', 'ink' => '#f6f6f6', 'muted' => '#c2c2c2', 'line' => '#757575', 'accent' => '#dddddd', 'footer' => '#303030', 'backdrop' => '#111111', 'veil' => 'rgba(17, 17, 17, 0.4)', 'highlight' => '#c4c4c4'],
    ],
    'monochrome_charcoal' => [
      'label' => 'モノクロ × チャコール',
      'light' => ['paper' => '#f3f3f3', 'ink' => '#1c1c1c', 'muted' => '#515151', 'line' => '#a4a4a4', 'accent' => '#333333', 'footer' => '#e5e5e5', 'backdrop' => '#dadada', 'veil' => 'rgba(228, 228, 228, 0.65)', 'highlight' => '#b5b5b5'],
      'dark' => ['paper' => '#191919', 'ink' => '#f0f0f0', 'muted' => '#b6b6b6', 'line' => '#686868', 'accent' => '#cecece', 'footer' => '#252525', 'backdrop' => '#090909', 'veil' => 'rgba(9, 9, 9, 0.4)', 'highlight' => '#ababab'],
    ],
  ];
}

/**
 * Normalizes missing or obsolete settings to the blue default.
 */
function clear_layers_resolve_palette(mixed $value): string {
  return is_string($value) && isset(clear_layers_color_palettes()[$value]) ? $value : 'ocean';
}
