((Drupal, once) => {
  Drupal.behaviors.searchBar = {
    attach(context) {
      once('searchbar', '.c-search-bar__button', context).forEach((toggle) => {
        const root = toggle.closest('.c-search-bar');
        const panel = root?.querySelector('.c-search-bar__content');
        if (!panel) {
          return;
        }

        const doc = root.ownerDocument;
        const label = toggle.querySelector('.c-search-bar__button-text');
        const narrow = window.matchMedia('(width < 48rem)');
        const searchInput = panel.querySelector('input[type="search"], input[type="text"]');
        const openLabel = toggle.dataset.searchOpenLabel;
        const closeLabel = toggle.dataset.searchCloseLabel;

        const setExpanded = (expanded, restoreFocus = false) => {
          panel.hidden = !expanded;
          panel.setAttribute('aria-hidden', String(!expanded));
          toggle.setAttribute('aria-expanded', String(expanded));
          const text = expanded ? closeLabel : openLabel;
          toggle.setAttribute('aria-label', text);
          toggle.setAttribute('title', text);
          if (label) {
            label.textContent = text;
          }
          if (expanded) {
            searchInput?.focus();
          } else if (restoreFocus) {
            toggle.focus();
          }
        };

        setExpanded(false);
        root.setAttribute('data-search-ready', '');
        toggle.hidden = false;

        toggle.addEventListener('click', () => {
          setExpanded(panel.hidden);
        });
        doc.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            setExpanded(false, true);
          }
        });
        doc.addEventListener('pointerdown', (event) => {
          if (!panel.hidden && !root.contains(event.target)) {
            setExpanded(false);
          }
        });
        root.addEventListener('focusout', (event) => {
          if (event.relatedTarget && !root.contains(event.relatedTarget)) {
            setExpanded(false);
          }
        });
        narrow.addEventListener('change', () => {
          const hadFocus = panel.contains(doc.activeElement);
          setExpanded(false, hadFocus);
        });
      });
    },
  };
})(Drupal, once);
