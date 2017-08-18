# Workbench Moderation to Content Moderation Migration

This module migrates the Workbench Moderation module in Drupal 8.4 to the Content Moderation module in Drupal 8.4.

This module is designed to execute the following 8 steps in a recoverable fashion:

1. States and transitions are stored in key value (i.e. the Workflow entity is created)
2. Entity state maps are stored in key value
3. WBM uninstalled
4. Workflows installed
5. CM installed
6. States and transitions are migrated (i.e. the Workflow entity is created)
7. Entity state maps are migrated
8. All keyvalue temporary state is cleaned up except for a final "hey we migrated all the things successfully" that will get cleaned up on uninstall of the module

If any step fails, the opportunity should be there for a human to recover from the failed point and re-run the migration without having to start the process over.

# Disclaimer

This module is experimental and should NOT be used on a live production system. Thoroughly test before running. Recovery of data is not guaranteed.

# Tested Scenarios

This module so far has only been tested with the [https://github.com/josephdpurcell/drupal8_wbm2cm_concept-project](WBM2CM Drupal Profile) which constructs a scenario where a content type has Workbench Moderation enabled on 3 entities, and those entities are successfully migrated to Content Moderation.

# Untested Test Scenarios

* Workbench Moderation enabled on multiple entities with different workflows
* Default revisions
* Large data sets (i.e. > 1,000,000 entities)
* Translations

# Improvements

* Store state map as one row per entity
* Properly handle recovery scenarios
* Validate the migration before beginning
* Follow the Search API pattern for use of batch api to allow arbitrary tasks to be defined and processed
