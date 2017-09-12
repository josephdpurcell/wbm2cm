<?php

namespace Drupal\wbm2cm\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Batch process the WBM to CM migration.
 */
class MigrateBatchController implements ContainerInjectionInterface {

  /**
   * The batch manager for the migration.
   *
   * @var \Drupal\wbm2cm\BatchManager
   */
  protected $batchManager;

  /**
   * Instantiate the migrate batch controller.
   *
   * @param \Drupal\wbm2cm\BatchManager $batch_manager
   *   The batch manager for the migration.
   */
  public function __construct(BatchManager $batch_manager) {
    $this->batchManager = $batch_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wbm2cm.batch_manager')
    );
  }

  /**
   * Set the batch tasks and trigger batch process.
   */
  public function migrate() {
    $batch = [
      'title' => t('Migrating WBM to CM'),
      'operations' => [
        ['wbm2cm_step1', []],
        ['wbm2cm_step2', []],
        ['wbm2cm_step3', []],
        ['wbm2cm_step4', []],
        ['wbm2cm_step5', []],
        ['wbm2cm_step6', []],
        ['wbm2cm_step7', []],
        ['wbm2cm_step8', []],
      ],
      'finished' => 'wbm2cm_migrate_finished_callback',
      'file' => drupal_get_path('module', 'wbm2cm') . '/wbm2cm.migrate.inc',
    ];
    batch_set($batch);
    return batch_process();
  }

}
