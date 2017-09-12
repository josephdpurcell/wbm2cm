<?php

namespace Drupal\wbm2cm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wbm2cm\MigrateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to trigger batch processing if state is valid.
 */
class MigrateForm extends FormBase {

  /**
   * The WBM2CM migration manager service.
   *
   * @var \Drupal\wbm2cm\MigrateManager
   */
  protected $migrate;

  /**
   * {@inheritdoc}
   */
  public function __construct(MigrateManager $migrate_manager) {
    $this->migrate = $migrate_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wbm2cm.migrate_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $is_complete = $this->migrate->isComplete();
    if ($is_complete) {
      return $this->buildFormForComplete($form, $form_state);
    }
    else {
      return $this->buildFormForIncomplete($form, $form_state);
    }
  }

  /**
   * Build the form for when the migration is complete.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  protected function buildFormForComplete(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      // @todo link the the uninstall page
      '#value' => 'The migration is complete! You may now uninstall this module.',
    ];
    return $form;
  }

  /**
   * Build the form for when the migration is NOT complete.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  protected function buildFormForIncomplete(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      // @todo turn the URL into a link
      '#value' => 'This migration is experimental and is designed for Drupal 8.4 alpha, migrating from Workbench Moderation to Content Moderation. There are known issues and many untested scenarios. For more details, see https://www.drupal.org/node/2897870.',
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Migrate'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $messages = $this->migrate->getValidationMessages();
    foreach ($messages as $message_id => $message) {
      $form_state->setErrorByName($message_id, $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wbm2cm.migrate');
  }

}
