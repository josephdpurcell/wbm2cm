<?php

namespace Drupal\wbm2cm\Controller;

/**
 * Batch process the WBM to CM migration.
 */
class MigrateBatchController {

  public function migrate() {
    $batch = [
      'title' => t('Migrating WBM to CM'),
      'operations' => [
        ['wbm2cm_gather', []],
        ['wbm2cm_migrate', []],
      ],
      'finished' => 'wbm2cm_migrate_finished_callback',
      'file' => drupal_get_path('module', 'wbm2cm') . '/wbm2cm.migrate.inc',
    ];
    batch_set($batch);
    return batch_process();
  }

}
