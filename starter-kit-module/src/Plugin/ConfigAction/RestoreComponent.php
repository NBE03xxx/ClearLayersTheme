<?php

declare(strict_types=1);

namespace Drupal\clear_layers_starter_kit\Plugin\ConfigAction;

use Drupal\canvas\Entity\Component;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Restores exported versions through Canvas' config entity storage API.
 */
#[ConfigAction(
  id: 'clear_layersRestoreComponent',
  admin_label: new TranslatableMarkup('Restore saved Clear Layers component versions'),
  entity_types: ['component'],
)]
final class RestoreComponent implements ConfigActionPluginInterface {

  public function apply(string $configName, mixed $value): void {
    if (!is_array($value) || $configName !== 'canvas.component.' . ($value['id'] ?? '')) {
      throw new ConfigActionException('Component ID does not match the exported record.');
    }
    $storage = \Drupal::entityTypeManager()->getStorage('component');
    $component = $storage->load($value['id']);
    if (!$component instanceof Component || $component->get('source') !== $value['source'] || $component->get('source_local_id') !== $value['source_local_id']) {
      throw new ConfigActionException('The destination component implementation differs from the exported record.');
    }
    // setProperties cannot change active_version: it leaves loadedVersion
    // pointing at a historical version. Canvas storage re-derives it during a
    // config import. Sync mode also prevents setters from rejecting that
    // temporary state while all the record's properties are being copied.
    $component->setSyncing(TRUE);
    $component = $storage->updateFromStorageRecord($component, $value);
    $component->loadVersion($component->getActiveVersion());
    $component->save();
    $component->setSyncing(FALSE);
  }

}
