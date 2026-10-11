/**
 * @file
 * Components - Tabs Content
 */

import { A11yTabContent } from '@stierpm/a11y-components';

((Drupal, once) => {
  Drupal.behaviors.clear_layersTabsContent = {
    attach(context) {
      const tabs = once(
        'clear_layers-tabs-content',
        context.querySelectorAll('.c-tabs-content'),
      );

      tabs.forEach((tab) => {
        new A11yTabContent(tab, {
          navigationSelector: '.c-tabs-content__navigation',
          groupSelector: '.c-tabs-content__group',
          triggerSelector: '.c-tabs-content__trigger',
          expandedClass: 'is-active',
          breakpoint: 768,
        });
      });
    },
  };
})(Drupal, once);
