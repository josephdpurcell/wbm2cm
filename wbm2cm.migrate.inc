<?php

use Symfony\Component\HttpFoundation\RedirectResponse;

function wbm2cm_gather($type, $options = array(), &$context) {
  $migrate = \Drupal::service('wbm2cm.migrate_manager');

  $migrate->step1();

  $context['results'][] = $options;
  $context['message'] = 'Completing step 1: gathering state map, uninstall WBM, install CM...';
}

function wbm2cm_migrate($type, $options = array(), &$context) {
  $migrate = \Drupal::service('wbm2cm.migrate_manager');

  $migrate->step2();

  $context['results'][] = $options;
  $context['message'] = 'Completing step 2: updating all entities with values from state map...';
}

function wbm2cm_migrate_finished_callback($success, $results, $operations) {
  if ($success) {
    $message = t('Migration complete. You can now uninstall this module.');
  }
  else {
    $message = t('Finished with an error.');
  }
  drupal_set_message($message);

  return new RedirectResponse(\Drupal::url('wbm2cm.overview', [], ['absolute' => TRUE]));
}
