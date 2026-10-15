/**
 * @file
 * Hides empty folders in the Canvas Library's Components tab.
 */

((Drupal, once) => {
  const updateFolders = (canvas) => {
    const panel = canvas.querySelector(
      '[data-testid="canvas-library-components-tab-content"]',
    );
    if (!panel) {
      return;
    }

    panel.querySelectorAll('[data-canvas-folder-name]').forEach((row) => {
      // Canvas 1.12 renders the count immediately before the expand button.
      const count = row.lastElementChild?.previousElementSibling;
      // Hide the entire folder, including its collapsible contents.
      const folder = row.closest('div[data-state]')?.parentElement;
      if (!count || !folder || !panel.contains(folder)) {
        return;
      }

      // Leave a new folder visible while its name is being edited.
      folder.hidden =
        count.textContent.trim() === '0' && !row.querySelector('input');
    });
  };

  Drupal.behaviors.canvasLibraryDisplay = {
    attach(context) {
      once('canvas-library-display', '#canvas', context).forEach((canvas) => {
        let scheduled = false;
        const observer = new MutationObserver(() => {
          if (scheduled) {
            return;
          }
          scheduled = true;
          requestAnimationFrame(() => {
            scheduled = false;
            updateFolders(canvas);
          });
        });

        // React updates counts and replaces panels without Drupal reattaching.
        // Do not observe attributes: our own hidden changes must not loop.
        observer.observe(canvas, {
          childList: true,
          characterData: true,
          subtree: true,
        });
        updateFolders(canvas);
      });
    },
  };
})(Drupal, once);
