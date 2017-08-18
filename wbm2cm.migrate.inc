<?php

function wbm2cm_step1(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step1($context);
}

function wbm2cm_step2(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step2($context);
}

function wbm2cm_step3(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step3($context);
}

function wbm2cm_step4(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step4($context);
}

function wbm2cm_step5(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step5($context);
}

function wbm2cm_step6(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step6($context);
}

function wbm2cm_step7(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step7($context);
}

function wbm2cm_step8(&$context) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  $manager->step8($context);
}

function wbm2cm_migrate_finished_callback($success, $results, $operations) {
  $manager = \Drupal::service('wbm2cm.batch_manager');
  return $manager->finished($success, $results, $operations);
}

