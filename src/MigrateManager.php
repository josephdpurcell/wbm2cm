<?php

namespace Drupal\wbm2cm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\workflows\Entity\Workflow;
use Psr\Log\LoggerInterface;

/**
 * Manages migrating from WBM to CM.
 *
 * The intention is for the migration to have the following recovery points:
 *    1. States and transitions are stored in key value (i.e. the Workflow entity is created)
 *    2. Entity state maps are stored in key value
 *    3. WBM uninstalled
 *    4. Workflows installed
 *    5. CM installed
 *    6. States and transitions are migrated (i.e. the Workflow entity is created)
 *    7. Entity state maps are migrated
 *    8. All keyvalue temporary state is cleaned up except for a final "hey we
 *      migrated all the things successfully" that will get cleaned up on
 *      uninstall of the module
 * Each recovery checkpoint means that the batch processing is designed to
 * either skip that step if it is complete, or re-try that step.
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
   * The key value store for the wbm2cm module.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Instantiate the MigrateManager service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value store factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, ModuleInstallerInterface $module_installer, KeyValueFactoryInterface $key_value_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleInstaller = $module_installer;
    $this->keyValueStore = $key_value_factory->get('wbm2cm');
    $this->logger = $logger;
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

  /**
   * Save the Workbench Moderation states and transitions.
   */
  public function saveWorkbenchModerationStatesAndTransitions() {
    // Collect all states.
    $states = [];
    foreach ($this->configFactory->listAll('workbench_moderation.moderation_state.') as $state_ids) {
      $state = $this->configFactory->getEditable($state_ids);
      $states[] = $state->get();
    }

    $this->logger->info('Found Workbench Moderation states: %state_ids', [
      '%state_ids' => print_r($states, 1),
    ]);

    // Save states.
    $this->keyValueStore->set('states', $states);

    // Collect all transitions.
    $transitions = [];
    foreach ($this->configFactory->listAll('workbench_moderation.moderation_state_transition.') as $transition_ids) {
      $transition = $this->configFactory->getEditable($transition_ids);
      $transitions[] = $transition->get();
    }

    $this->logger->info('Found Workbench Moderation transitions: %transition_ids', [
      '%transition_ids' => print_r($transitions, 1),
    ]);

    // Save transitions.
    $this->keyValueStore->set('transitions', $transitions);
  }

  /**
   * Save the Workbench Moderation states on all entities.
   */
  public function saveWorkbenchModerationSateMap() {
    // @todo avoid needing to save enabled bundles?
    // Collect all moderated bundles.
    $this->entityTypeManager->clearCachedDefinitions();
    // @todo consider leveraging WBM to get the list of enabled bundles?
    $enabled_bundles = [];
    foreach ($this->configFactory->listAll() as $bundle_config_id) {
      $bundle_config = $this->configFactory->getEditable($bundle_config_id);
      if (!$third_party_settings = $bundle_config->get('third_party_settings')) {
        $this->logger->debug('Skipping entity bundle that is not moderated: %bundle_id', [
          '%bundle_id' => $bundle_config_id,
        ]);
        continue;
      }
      $third_party_settings_updated = array_diff_key($third_party_settings, array_flip(['workbench_moderation']));
      if (count($third_party_settings) !== count($third_party_settings_updated)) {
        $this->logger->debug('Found Workbench Moderation bundle that is moderated: %bundle_id', [
          '%bundle_id' => $bundle_config_id,
        ]);
        // Collect which entity types and bundles have moderation enabled.
        list($entity_provider, $bundle_config_prefix, $bundle_id) = explode('.', $bundle_config_id);
        $entity_type_id = FALSE;
        foreach ($this->entityTypeManager->getDefinitions() as $entity_definition) {
          if ($entity_definition->getProvider() === $entity_provider && $entity_definition->get('config_prefix') === $bundle_config_prefix) {
            $entity_type_id = $entity_definition->getBundleOf();
            break;
          }
        }
        // @todo cleanup and clarify what error state we are dealing with
        if (!$entity_type_id) {
          throw new \Exception('Something went wrong.');
        }
        $enabled_bundles[$entity_type_id][] = $bundle_id;
      }
      else {
        $this->logger->debug('Skipping Workbench Moderation bundle that is moderated, but is incorrect format? %bundle_id', [
          '%bundle_id' => $bundle_config_id,
        ]);
      }
    }

    // Save enabled bundles.
    $this->keyValueStore->set('enabled_bundles', $enabled_bundles);

    // Collect entity state map and remove Workbench moderation_state field from
    // enabled bundles.
    $state_map = [];
    foreach ($enabled_bundles as $entity_type_id=> $bundles) {
      $state_map[$entity_type_id] = [];
      foreach ($bundles as $bundle) {
        $state_map[$entity_type_id][$bundle] = [];
        $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
        $this->logger->debug('Querying for all %bundle_id revisions...', [
          '%bundle_id' => $bundle,
        ]);
        $entity_revisions = \Drupal::entityQuery($entity_type_id)
          ->condition('type', $bundle)
          ->allRevisions()
          ->execute();

        foreach ($entity_revisions as $revision_id => $id) {
          $entity = $entity_storage->loadRevision($revision_id);
          $state_map[$entity_type_id][$bundle][$revision_id] = $entity->moderation_state->target_id;
// @joe comment out these lines
          /* Uncomment these lines for extremely verbose debugging. This is not recommended on large data sets. */
          $this->logger->debug('Setting Workbench Moderation state field on id:%id, revision:%revision_id from %state to NULL', [
            '%id' => $id,
            '%revision_id' => $revision_id,
            '%state' => $entity->moderation_state->target_id,
          ]);
          $entity->moderation_state = NULL;
          $entity->save();
        }
      }
    }
    $this->logger->notice('Workbench Moderation states have been removed from all entities and temporarily stored in key value storage.');
    $this->keyValueStore->set('state_map', $state_map);
  }

  /**
   * Uninstall the Workbench Moderation module.
   */
  public function uninstallWorkbenchModeration() {
    // @todo check if WBM is already uninstalled, if so return
    // Uninstall Workbench Moderation, but not its dependencies.
    $this->moduleInstaller->uninstall(['workbench_moderation'], FALSE);
    $this->logger->notice('Workbench Moderation module is uninstalled.');
  }

  /**
   * Install the Workflows module.
   */
  public function installWorkflows() {
    // @todo check if workflows is already installed, if so return
    // Install Workflows module.
    // Note: this will trigger Workbench Moderation to not be "active" so that it
    // can be disabled without database integrity errors.
    $this->moduleInstaller->install(['workflows']);
    $this->logger->notice('Workflows module is installed.');
  }

  /**
   * Install the Content Moderation module.
   */
  public function installContentModeration() {
    // @todo check if content moderation is already installed, if so return
    $this->moduleInstaller->install(['content_moderation']);
    $this->logger->notice('Content Moderation module is installed.');
  }

  /**
   * Create the Workflow based on info from WBM states and transitions.
   */
  public function recreateWorkbenchModerationWorkflow() {
    $states = $this->keyValueStore->get('states');
    $transitions = $this->keyValueStore->get('transitions');
    $enabled_bundles = $this->keyValueStore->get('enabled_bundles');

    // Create and save a workflow entity with the information gathered.
    // Note: this implies all entities will be squished into a single workflow.
    // @todo figure out how to use classes to add states and transitions instead of an array
    $workflow_config = [
      'id' => 'content_moderation_workflow',
      'label' => 'Content Moderation Workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [],
        'transitions' => [],
      ],
    ];
    foreach ($states as $state) {
      $workflow_config['type_settings']['states'][$state['id']] = [
        'label' => $state['label'],
        'published' => $state['published'],
        'default_revision' => $state['default_revision'],
      ];
    }

    foreach ($transitions as $transition) {
      $workflow_config['type_settings']['transitions'][$transition['id']] = [
        'label' => $transition['label'],
        'to' => $transition['stateTo'],
        'from' => explode(',', $transition['stateFrom']),
      ];
    }

    // Instantiate the workflow from the config.
    $this->logger->info('Create workflow from config: %config', [
      '%config' => print_r($workflow_config, 1),
    ]);
    $workflow = new Workflow($workflow_config, 'workflow');
    $workflow_type_plugin = $workflow->getTypePlugin();

    // Add Content Moderation moderation to bundles that were Workbench Moderation moderated.
    foreach ($enabled_bundles as $entity_type_id=> $bundles) {
      foreach ($bundles as $bundle) {
        $workflow->getTypePlugin()->addEntityTypeAndBundle($entity_type_id, $bundle);
        $this->logger->notice('Setting Content Moderation to be enabled on %bundle_id', [
          '%bundle_id' => $bundle,
        ]);
      }
    }

    // Save the workflow now that it has all the configurations set.
    $workflow->save();
    $this->logger->notice('Content Moderation Workflow created.');
  }

  /**
   * Create the moderation states on all entities based on WBM data.
   */
  public function recreateModerationStatesOnEntities() {
    $state_map = $this->keyValueStore->get('state_map');

    // Set the new moderation state.
    foreach ($state_map as $entity_type_id => $bundles) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      foreach ($bundles as $bundle => $entities) {
        $this->logger->debug('Setting Content Moderation states on %bundle_id entities', [
          '%bundle_id' => $bundle,
        ]);
        foreach ($entities as $revision_id => $state_id) {
          $entity = $entity_storage->loadRevision($revision_id);
          $entity->moderation_state = $state_id;
          $entity->save();
// @joe comment out these lines
          /* Uncomment these lines for extremely verbose debugging. This is not recommended on large data sets. */
          $this->logger->debug('Setting Workbench Moderation state field on id:%id, revision:%revision_id to %state', [
            '%id' => $entity->id(),
            '%revision_id' => $revision_id,
            '%state' => $state_id,
          ]);
        }
      }
    }

    // @todo figure out why /node/{id}/edit does not let you edit body field
  }

}
