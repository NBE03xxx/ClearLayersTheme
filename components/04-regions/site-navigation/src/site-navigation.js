((Drupal, once) => {
  Drupal.behaviors.clear_layersSiteNavigation = {
    attach(context) {
      once('clear_layers-site-navigation', '[data-site-navigation]', context).forEach((wrapper) => {
        const toggle = wrapper.querySelector('[data-navigation-toggle]');
        const panel = wrapper.querySelector('[data-navigation-panel]');
        const desktop = window.matchMedia('(min-width: 64rem)');

        const setOpen = (open) => {
          toggle.setAttribute('aria-expanded', String(open));
          panel.hidden = !open;
        };

        const updateLayout = () => {
          // Keep focused links reachable when resizing to a small screen.
          const focusInPanel = panel.contains(document.activeElement);
          if (desktop.matches && document.activeElement === toggle) {
            wrapper.focus();
          }
          toggle.hidden = desktop.matches;
          setOpen(desktop.matches || focusInPanel);
        };

        toggle.addEventListener('click', () => {
          setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        wrapper.addEventListener('focusin', (event) => {
          // The skip-navigation link targets the region wrapper.
          if (event.target === wrapper) {
            setOpen(true);
          }
        });

        wrapper.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || event.defaultPrevented || desktop.matches || panel.hidden) {
            return;
          }
          event.preventDefault();
          setOpen(false);
          toggle.focus();
        });

        desktop.addEventListener('change', updateLayout);
        updateLayout();
      });

      once('clear_layers-navigation-submenu', '[data-site-navigation] [data-submenu-toggle]', context).forEach((button) => {
        const item = button.closest('.site-navigation__item');
        const submenu = item.querySelector('.site-navigation__submenu');
        if (!submenu || submenu.id !== button.getAttribute('aria-controls')) {
          return;
        }

        const setOpen = (open) => {
          button.setAttribute('aria-expanded', String(open));
          submenu.hidden = !open;
        };

        button.hidden = false;
        setOpen(false);
        button.addEventListener('click', () => {
          setOpen(button.getAttribute('aria-expanded') !== 'true');
        });

        item.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape' || event.defaultPrevented || submenu.hidden) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          setOpen(false);
          button.focus();
        });
      });
    },
  };
})(Drupal, once);
