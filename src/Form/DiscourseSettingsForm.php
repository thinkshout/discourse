<?php

namespace Drupal\discourse\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Discourse Settings.
 */
class DiscourseSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'discourse.discourse_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'discourse_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('discourse.discourse_settings');
    $form['base_url_of_discourse'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL of Discourse'),
      '#description' => $this->t('Please enter url without trailing / character. Example: https://test.trydiscourse.com'),
      '#maxlength' => 256,
      '#size' => 64,
      '#default_value' => $config->get('base_url_of_discourse'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api Key'),
      '#maxlength' => 256,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('api_key'),
    ];

    $form['api_user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api Username'),
      '#maxlength' => 256,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('api_user_name'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('discourse.discourse_settings')
      ->set('base_url_of_discourse', $form_state->getValue('base_url_of_discourse'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_user_name', $form_state->getValue('api_user_name'))
      ->save();
  }

}
