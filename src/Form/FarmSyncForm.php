<?php

namespace Drupal\farm_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_sync\farmOS;

/**
 * Implements the FarmSyncForm form controller.
 */
class FarmSyncForm extends FormBase {

  /**
   * Build the farmOS sync form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync'),
    ];

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId() {
    return 'farm_sync_form';
  }

  /**
   * Implements form validation.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Implements a form submit handler.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Instantiate the Drupal messenger.
    $messenger = \Drupal::messenger();

    // Get the hostname, username, and password from configuration.
    $hostname = \Drupal::config('farm_sync.settings')->get('hostname');
    $username = \Drupal::config('farm_sync.settings')->get('username');
    $password = \Drupal::config('farm_sync.settings')->get('password');

    // Create a new farmOS API instance.
    $farmOS = new farmOS($hostname, $username, $password);

    // Authenticate with farmOS.
    $authenticated = $farmOS->authenticate();

    // If authentication failed, print a message and bail.
    if (empty($authenticated)) {
      $message = $this->t('farmOS authentication failed. Refer to the watchdog logs for more information.');
      $messenger->addMessage($message, $messenger::TYPE_WARNING);
      return;
    }

    // Success!
    $messenger->addMessage('Success!');
  }
}
