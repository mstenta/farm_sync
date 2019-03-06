<?php

namespace Drupal\farm_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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

    // Add a set of checkboxes for records that can be synced.
    $record_options = [
      'areas' => $this->t('Areas'),
    ];
    $form['records'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Sync records'),
      '#options' => $record_options,
      '#required' => TRUE,
    );

    // Add a text field that allows the user to specify what type of areas
    // should be synced.
    $form['area_type'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Area type (machine name)'),
      '#description' => $this->t('Optionally specify a specific area type that should be synced (using the area type machine name from farmOS). For example: "building" or "field"'),
    );

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

    // Iterate through the selected record types and assemble a list of batch
    // operations.
    $record_types = $form_state->getValue('records');
    $operations = [];
    foreach ($record_types as $type => $name) {

      // Each operation will consist of a function name and an array of other
      // arguments that will be passed into the function.
      $function = 'farm_sync_batch';
      $arguments = [
        'entity_type' => '',
        'filters' => [],
      ];

      // Switch through the supported record types to add additional arguments.
      // The farm_sync_batch() function expects 'entity_type' and 'filters' for
      // use in the getRecords() method.
      switch ($type) {

        // Areas.
        case 'areas':
          $arguments['entity_type'] = 'taxonomy_term';
          $arguments['filters']['bundle'] = 'farm_areas';
          $area_type = $form_state->getValue('area_type');
          if (!empty($area_type)) {
            $arguments['filters']['area_type'] = $area_type;
          }
          break;

        // If no match was found, skip it.
        default:
          continue;
      }

      // Add the operation.
      $operations[] = [
        $function,
        [$arguments],
      ];
    }

    // If no operations were added, bail.
    if (empty($operations)) {
      return;
    }

    // Run the batch operation.
    $batch = [
      'operations' => $operations,
      'finished' => 'farm_sync_batch_finished',
      'title' => $this->t('Processing record sync'),
      'init_message' => $this->t('Record sync is starting.'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('Record sync has encountered an error.'),
    ];
    batch_set($batch);
  }
}
