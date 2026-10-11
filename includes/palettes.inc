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
      'label' => 'オーシャンブルー（青 × シアン）',
      'light' => ['paper' => '#edf6ff', 'ink' => '#122b49', 'muted' => '#405671', 'line' => '#9eb6d3', 'accent' => '#0753b5', 'footer' => '#dfedff', 'backdrop' => '#dceaff', 'veil' => 'rgba(225, 239, 255, 0.65)', 'highlight' => '#58c8ff'],
      'dark' => ['paper' => '#0c2039', 'ink' => '#edf6ff', 'muted' => '#abc3df', 'line' => '#4c708f', 'accent' => '#8bd5ff', 'footer' => '#102b49', 'backdrop' => '#06152c', 'veil' => 'rgba(5, 18, 44, 0.4)', 'highlight' => '#35bcff'],
    ],
    'arctic' => [
      'label' => 'アイスブルー（淡い青 × アクア）',
      'light' => ['paper' => '#f1fbff', 'ink' => '#143147', 'muted' => '#425e70', 'line' => '#a0bccd', 'accent' => '#00618b', 'footer' => '#e3f6ff', 'backdrop' => '#e3f5fd', 'veil' => 'rgba(230, 247, 255, 0.65)', 'highlight' => '#88e5fa'],
      'dark' => ['paper' => '#0d2635', 'ink' => '#ecfbff', 'muted' => '#a8cad6', 'line' => '#496f80', 'accent' => '#8bddf2', 'footer' => '#123746', 'backdrop' => '#061a29', 'veil' => 'rgba(6, 26, 41, 0.4)', 'highlight' => '#88e5fa'],
    ],
    'indigo' => [
      'label' => 'インディゴ（藍色 × ラベンダー）',
      'light' => ['paper' => '#f3f2ff', 'ink' => '#24274d', 'muted' => '#555674', 'line' => '#a8acd4', 'accent' => '#4b4ab5', 'footer' => '#e9e8ff', 'backdrop' => '#e6e7fb', 'veil' => 'rgba(238, 235, 255, 0.65)', 'highlight' => '#adb0ff'],
      'dark' => ['paper' => '#191b3a', 'ink' => '#f2f1ff', 'muted' => '#c1bfe3', 'line' => '#686895', 'accent' => '#bfbaff', 'footer' => '#27274f', 'backdrop' => '#0c112c', 'veil' => 'rgba(12, 17, 44, 0.4)', 'highlight' => '#b0abff'],
    ],
    'lagoon' => [
      'label' => 'ブルーティール（青緑 × ミント）',
      'light' => ['paper' => '#edfafa', 'ink' => '#123936', 'muted' => '#3c5b58', 'line' => '#9abfb9', 'accent' => '#006762', 'footer' => '#dbf1ed', 'backdrop' => '#dcefea', 'veil' => 'rgba(224, 248, 239, 0.65)', 'highlight' => '#65d8d1'],
      'dark' => ['paper' => '#0b282d', 'ink' => '#e8fcf9', 'muted' => '#a2ccc6', 'line' => '#497e7a', 'accent' => '#80e0d2', 'footer' => '#12383c', 'backdrop' => '#061c26', 'veil' => 'rgba(6, 28, 38, 0.4)', 'highlight' => '#55d3c2'],
    ],
  ];
}

/**
 * Normalizes missing or obsolete settings to the blue default.
 */
function clear_layers_resolve_palette(mixed $value): string {
  return is_string($value) && isset(clear_layers_color_palettes()[$value]) ? $value : 'ocean';
}
