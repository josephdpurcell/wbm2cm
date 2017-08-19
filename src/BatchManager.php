<?php

namespace Drupal\wbm2cm;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Manages communication between Batch API and the migration manager.
 */
class BatchManager {

  /**
   * Processing operations should be stopped.
   *
   * @var bool
   */
  protected $isProcessingStopped = FALSE;

  /**
   * The migration manager.
   *
   * @var \Drupal\wbm2cm\MigrateManager
   */
  protected $manager;

  /**
   * The key value store for the wbm2cm module.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * Instantiate BatchManager.
   *
   * @param \Drupal\wbm2cm\MigrateManager $manager
   *   The migration manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value store factory.
   */
  public function __construct(MigrateManager $manager, KeyValueFactoryInterface $key_value_factory) {
    $this->manager = $manager;
    $this->batchStore = $key_value_factory->get('wbm2cm_batch');
  }

  /**
   * Determine if a particular step is complete.
   *
   * @param string $step
   *   The name of the step, e.g. "step1".
   *
   * @return bool
   *   True if complete and no action needs taken, else false.
   */
  public function isStepComplete($step) {
    if ($this->batchStore->has($step)) {
      return 'complete' == $this->batchStore->get($step);
    }
    return FALSE;
  }

  /**
   * Set a particular step to complete.
   *
   * @param string $step
   *   The name of the step, e.g. "step1".
   */
  public function setStepComplete($step) {
    $this->batchStore->set($step, 'complete');
  }

  /**
   * Set a particular step to incomplete.
   *
   * @param string $step
   *   The name of the step, e.g. "step1".
   */
  public function setStepIncomplete($step) {
    $this->batchStore->set($step, 'incomplete');
  }

  /**
   * Stop processing operations.
   */
  protected function stopProcessing() {
    $this->isProcessingStopped = TRUE;
  }

  /**
   * Determine if operations should stop processing.
   *
   * @return bool
   *   True if operations should not be processed, else false.
   */
  protected function isProcessingStopped() {
    return $this->isProcessingStopped;
  }

  /**
   * Determine if a particular step can be processed.
   *
   * @return bool
   *   True if it can be processed, else false.
   */
  public function isStepSkipped($step) {
    if ($this->isProcessingStopped()) {
      return TRUE;
    }
    if ($this->isStepComplete($step)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * States and transitions are stored in key value (i.e. the Workflow entity is created).
   */
  public function step1(&$context) {
    if ($this->isStepSkipped('step1')) {
      return;
    }
    $this->manager->saveWorkbenchModerationStatesAndTransitions();
    $this->setStepComplete('step1');
    $context['message'] = 'Saving Workbench Moderation states and transitions to key value storage.';
  }

  /**
   * Entity state maps are stored in key value.
   */
  public function step2(&$context) {
    if ($this->isStepSkipped('step2')) {
      return;
    }
    $this->manager->saveWorkbenchModerationSateMap();
    $context['message'] = 'Saving Workbench Moderation entity states to key value storage.';
  }

  /**
   * WBM uninstalled.
   */
  public function step3(&$context) {
    if ($this->isStepSkipped('step3')) {
      return;
    }
    $this->manager->uninstallWorkbenchModeration();
    $this->setStepComplete('step3');
    $context['message'] = 'Uninstalling Workbench Moderation.';
  }

  /**
   * Workflows installed.
   */
  public function step4(&$context) {
    if ($this->isStepSkipped('step4')) {
      return;
    }
    $this->manager->installWorkflows();
    $this->setStepComplete('step4');
    $context['message'] = 'Installing Workflows module.';
  }

  /**
   * CM installed.
   */
  public function step5(&$context) {
    if ($this->isStepSkipped('step5')) {
      return;
    }
    $this->manager->installContentModeration();
    $this->setStepComplete('step5');
    $context['message'] = 'Installing Content Moderation module.';
  }

  /**
   * States and transitions are migrated (i.e. the Workflow entity is created).
   */
  public function step6(&$context) {
    if ($this->isStepSkipped('step6')) {
      return;
    }
    $this->manager->recreateWorkbenchModerationWorkflow();
    $this->setStepComplete('step6');
    $context['message'] = 'Importing states and transitions from key value storage to Workflows.';
  }

  /**
   * Entity state maps are migrated.
   */
  public function step7(&$context) {
    if ($this->isStepSkipped('step7')) {
      return;
    }
    $this->manager->recreateModerationStatesOnEntities();
    $this->setStepComplete('step7');
    $context['message'] = 'Importing entity moderation states from key value storage to Content Moderation.';
  }

  /**
   * All keyvalue temporary state is cleaned up except for progress state.
   */
  public function step8(&$context) {
    if ($this->isStepSkipped('step8')) {
      return;
    }
    $this->manager->cleanupKeyValue();
    $this->setStepComplete('step8');

    // This is the last step, so set the migration as finished.
    $this->manager->setFinished();

    $context['message'] = 'Clean up key value storage.';
  }

  /**
   * Finalize the batch process.
   */
  public function finished($success, $results, $operations) {
    if ($success) {
      $message = t('Migration complete. You can now uninstall this module.');
    }
    else {
      // @todo change this to useful error message
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);

    return new RedirectResponse(\Drupal::url('wbm2cm.overview', [], ['absolute' => TRUE]));
  }

  /**
   * Purge all key value stores used by the batch manager.
   *
   * Note: this should only be used during the module's uninstall.
   */
  public function purgeAllKeyValueStores() {
    $this->batchStore->deleteAll();
  }

}
