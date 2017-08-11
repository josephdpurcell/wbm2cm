<?php

namespace Drupal\wbm2cm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Manages migrating from WBM to CM.
 */
class MigrateManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   */
  protected $moduleInstaller;

  /**
   * Instantiate the MigrateManager service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, ModuleInstallerInterface $module_installer) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleInstaller = $module_installer;
  }

  /**
   * Get messages for whether or not the migration is valid and ready.
   *
   * @return string[]
   *   An array of messages to display to the user about, keyed on a unique
   *   identifier.
   */
  public function getValidationMessages() {
    $messages = [];
    // @todo validate WBM does not have multiple transitions, see WorkflowTypeBase
    // @todo validate that there is a 'draft' and 'published' state, which are required
    // @todo check if migration was run and failed, i.e. we need to trigger a "re-run" from a failed state
    return $messages;
  }

  /**
   * Determine if the migration is valid.
   *
   * @return bool
   *   True if the migration is valid and ready to begin.
   */
  public function isValid() {
    return empty($this->getValidationMessages());
  }

  /**
   * Determine if the migration is complete.
   *
   * @return bool
   *   True if the migration completed successfully. Otherwise, false.
   */
  public function isComplete() {
    // @todo return a value if it is complete
  }

  // @todo use key value store
  protected $states;
  protected function setModerationStateMap(array $states) {
    $this->states = $states;
  }
  protected function getModerationStateMap() {
    return $this->states;
  }

  /**
   * Step 1:
   *   - Gather the moderation states and save them.
   *   - Migrate WBM states and transitions to CM.
   *   - Uninstall WBM.
   */
  public function step1() {
    $this->entityTypeManager->clearCachedDefinitions();

    // Collect all states.
    $states = [];
#    error_log("Collecting moderation state IDs:");
    foreach ($this->configFactory->listAll('workbench_moderation.moderation_state.') as $state_ids) {
#      error_log("    $state_ids");
      $state = $this->configFactory->getEditable($state_ids);
      $states[] = $state->get();
    }
#    error_log("\n");

#    error_log(print_r($states,1));

    // Collect all transitions.
    $transitions = [];
#    error_log("Collecting state transition IDs:");
    foreach ($this->configFactory->listAll('workbench_moderation.moderation_state_transition.') as $transition_ids) {
#      error_log("    $transition_ids");
      $transition = $this->configFactory->getEditable($transition_ids);
      $transitions[] = $transition->get();
    }
#    error_log("\n");

#    error_log(print_r($transitions,1));

    // Collect all moderated bundles.
    // @todo consider leveraging WBM to get the list of enabled bundles?
    $enabled_bundles = [];
#    error_log("Get bundles that were Workbench Moderated:");
    foreach ($this->configFactory->listAll() as $bundle_config_id) {
      $bundle_config = $this->configFactory->getEditable($bundle_config_id);
      if (!$third_party_settings = $bundle_config->get('third_party_settings')) {
        continue;
      }
      $third_party_settings_updated = array_diff_key($third_party_settings, array_flip(['workbench_moderation']));
      if (count($third_party_settings) !== count($third_party_settings_updated)) {
#        error_log("  HIT: {$bundle_config_id}");
        // Collect which entity types and bundles have moderation enabled.
        list($entity_provider, $bundle_config_prefix, $bundle_id) = explode('.', $bundle_config_id);
        $entity_type_id = FALSE;
        foreach ($this->entityTypeManager->getDefinitions() as $entity_definition) {
          if ($entity_definition->getProvider() === $entity_provider && $entity_definition->get('config_prefix') === $bundle_config_prefix) {
            $entity_type_id = $entity_definition->getBundleOf();
            break;
          }
        }
        if (!$entity_type_id) {
          throw new \Exception('Something went wrong.');
        }
        $enabled_bundles[$entity_type_id][] = $bundle_id;
      }
      else {
#        error_log("  PASS: {$bundle_config_id}");
      }
    }
#    error_log("\n");

    // Collect entity state map and remove Workbench moderation_state field from
    // enabled bundles.
    $state_map = [];
#    error_log("Remove Workbench moderation_state field from enabled bundles:");
    foreach ($enabled_bundles as $entity_type_id=> $bundles) {
      $state_map[$entity_type_id] = [];
#      error_log("    {$entity_type_id}:");
      foreach ($bundles as $bundle) {
        $state_map[$entity_type_id][$bundle] = [];
        $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
#        error_log("        Querying all {$bundle} revisions...");
        $entity_revisions = \Drupal::entityQuery($entity_type_id)
          ->condition('type', $bundle)
          ->allRevisions()
          ->execute();

        foreach ($entity_revisions as $revision_id => $id) {
          $entity = $entity_storage->loadRevision($revision_id);
          $state_map[$entity_type_id][$bundle][$revision_id] = $entity->moderation_state->target_id;
#          error_log("        id:{$id}, revision:{$revision_id} - setting moderation_state of {$entity->moderation_state->target_id} to NULL");
          $entity->moderation_state = NULL;
          $entity->save();
        }
      }
    }
#    error_log("\n");
    $this->setModerationStateMap($state_map);

    // Uninstall Workbench Moderation, but not its dependencies.
#    error_log("Uninstalling workbench_moderation module.");
    $this->moduleInstaller->uninstall(['workbench_moderation'], FALSE);
#    error_log("\n");

    // -----------------------------------------------------------------------------
    // Part II. Use collected info to enable Content Moderation.
    // -----------------------------------------------------------------------------

    // Install Workflows module.
    // Note: this will trigger Workbench Moderation to not be "active" so that it
    // can be disabled without database integrity errors.
#    error_log("Installing workflows module");
    $this->moduleInstaller->install(['workflows']);
#    error_log("Installing content_moderation module");
    $this->moduleInstaller->install(['content_moderation']);
#    error_log("\n");

    // Create and save a workflow entity with the information gathered.
    // Note: this implies all entities will be squished into a single workflow.
    $workflow_config = [
      'id' => 'content_moderation_workflow',
      'label' => 'Content Moderation Workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [],
        'transitions' => [],
      ],
    ];
#    error_log("Add states to Workflow:");
    foreach ($states as $state) {
#      error_log("    {$state['id']}:{$state['label']}");
      //$workflow->addState($state['id'], $state['label']);
      //$workflow_type_plugin->addState($state['id'], $state['label']);
      $workflow_config['type_settings']['states'][$state['id']] = [
        'label' => $state['label'],
        'published' => $state['published'],
        'default_revision' => $state['default_revision'],
      ];
    }
#    error_log("\n");

#    error_log("Add transitions to Workflow:");
    foreach ($transitions as $transition) {
#      error_log("    {$transition['id']}:{$transition['label']}    [{$transition['stateFrom']}] -> {$transition['stateTo']}");
      $workflow_config['type_settings']['transitions'][$transition['id']] = [
        'label' => $transition['label'],
        'to' => $transition['stateTo'],
        'from' => explode(',', $transition['stateFrom']),
      ];
    }
#    error_log("\n");

    // Instantiate the workflow from the config.
    $workflow = new Workflow($workflow_config, 'workflow');
    $workflow_type_plugin = $workflow->getTypePlugin();

    // Add Content Moderation moderation to bundles that were Workbench Moderation moderated.
#    error_log("Enable Content Moderation on bundles:");
    foreach ($enabled_bundles as $entity_type_id=> $bundles) {
      foreach ($bundles as $bundle) {
        $workflow->getTypePlugin()->addEntityTypeAndBundle($entity_type_id, $bundle);
#        error_log("    Enabling Content Moderation on {$bundle}");
      }
    }
#    error_log("\n");

    // Save the workflow now that it has all the configurations set.
    $workflow->save();

  }

  /**
   * Step 2: Perform the migration from WBM to CM.
   *
   * @return bool
   *   True if migration succeeded. False if it failed, or further action is
   *   required.
   */
  public function step2() {
    $state_map = $this->getModerationStateMap();

    // Set the new moderation state.
#    error_log("Set content moderation state on entities:");
    foreach ($state_map as $entity_type_id => $bundles) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      foreach ($bundles as $bundle => $entities) {
#        error_log("    Processing bundle {$entity_type_id}:{$bundle}");
        foreach ($entities as $revision_id => $state_id) {
          $entity = $entity_storage->loadRevision($revision_id);
#          error_log("        {$entity->id()}, revision:{$revision_id} - setting moderation_state to {$state_id}");
          $entity->moderation_state = $state_id;
          $entity->save();
        }
      }
    }
#    error_log("\n");

    // @todo figure out why node entities do not let you edit the body field

    // -----------------------------------------------------------------------------
    // Part III. Profit.
    // -----------------------------------------------------------------------------
    //
    // You should now have a working Drupal that has 3 page nodes in different
    // moderation states. Workbench Moderation should be uninstalled, and the
    // Content Moderation and Workflows modules should be installed.
    //
    // Any states and transitions should now appear in the Workflows
    // configuration.
  }

}
