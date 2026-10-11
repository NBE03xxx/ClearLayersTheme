((Drupal, once) => {
  const storageKey = 'jisaba-color-scheme';
  const normalize = (value) => (['light', 'dark'].includes(value) ? value : 'system');

  const readPreference = () => {
    try {
      return normalize(window.localStorage.getItem(storageKey));
    } catch {
      return 'system';
    }
  };

  let currentPreference = readPreference();
  const systemScheme = window.matchMedia('(prefers-color-scheme: dark)');

  const getNextPreference = () => {
    // Start with the opposite appearance so the first click visibly changes it.
    const modes = systemScheme.matches
      ? ['system', 'light', 'dark']
      : ['system', 'dark', 'light'];
    return modes[(modes.indexOf(currentPreference) + 1) % modes.length];
  };

  const applyPreference = (value) => {
    currentPreference = value;
    if (value === 'system') {
      document.documentElement.removeAttribute('data-color-scheme');
    } else {
      document.documentElement.setAttribute('data-color-scheme', value);
    }
    const effectiveScheme = value === 'system'
      ? (systemScheme.matches ? 'dark' : 'light')
      : value;
    document.querySelectorAll('[data-color-scheme-control]').forEach((control) => {
      const button = control.querySelector('[data-color-scheme-button]');
      if (!button) {
        return;
      }
      const modeLabel = control.dataset[`${value}Label`];
      const currentLabel = value === 'system'
        ? `${modeLabel} (${control.dataset[`${effectiveScheme}Label`]})`
        : modeLabel;
      const actionLabel = Drupal.t('@label：@current。クリックして@nextへ切り替え。', {
        '@label': control.dataset.label,
        '@current': currentLabel,
        '@next': control.dataset[`${getNextPreference()}Label`],
      });
      button.dataset.mode = value;
      button.dataset.effectiveScheme = effectiveScheme;
      button.setAttribute('aria-label', actionLabel);
      button.setAttribute('title', actionLabel);
      control.querySelector('[data-color-scheme-label]').textContent = currentLabel;
    });
  };

  Drupal.behaviors.clear_layersColorScheme = {
    attach(context) {
      once('jisaba-color-scheme', '[data-color-scheme-control]', context).forEach((control) => {
        const button = control.querySelector('[data-color-scheme-button]');
        if (!button) {
          return;
        }
        applyPreference(currentPreference);
        control.hidden = false;
        button.addEventListener('click', () => {
          const value = getNextPreference();
          applyPreference(value);
          try {
            if (value === 'system') {
              window.localStorage.removeItem(storageKey);
            } else {
              window.localStorage.setItem(storageKey, value);
            }
          } catch {
            // The button also works when browser storage is unavailable.
          }
        });
      });
    },
  };

  systemScheme.addEventListener('change', () => {
    applyPreference(currentPreference);
  });

  window.addEventListener('storage', (event) => {
    if (event.key === storageKey || event.key === null) {
      applyPreference(readPreference());
    }
  });
})(Drupal, once);
