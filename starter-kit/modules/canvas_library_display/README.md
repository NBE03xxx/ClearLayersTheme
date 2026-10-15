# Canvas Library Display

Hides folders whose displayed component count is zero in the Canvas Library's
Components tab. Folders reappear when their available component count increases.
Folder settings, disabled components, saved layouts, Patterns, and other panels
are retained. A folder remains visible while its name is being edited.

Bundled with Clear Layers under `starter-kit/modules/canvas_library_display`.
The theme's "Canvas の初期構成を作成" button enables this module together with the
taxonomy helper. Drupal discovers it in the theme directory; do not copy it to
`web/modules/custom` as well.

To enable it separately, use `vendor/bin/drush pm:enable canvas_library_display --yes`,
then run `vendor/bin/drush cache:rebuild` and reload Canvas. An existing
copy under `web/modules/custom` can be moved out of the site before clearing
caches; an already enabled module does not need to be uninstalled.

The module adds a small script only to `canvas/canvas-ui` through
`hook_library_info_alter()`. It follows the count already calculated by Canvas,
including visibility rules, instead of filtering the shared folders API.

The DOM integration targets Canvas 1.12's component panel test ID, folder name
attribute, count before the expand button, and collapsible wrapper. Recheck these
selectors when upgrading Canvas. Disabling this module restores empty folders
after clearing caches and reloading Canvas; no folder data is deleted.
