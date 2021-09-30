<?php

namespace Drupal\discourse_groups\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discourse Group Settings Form.
 */
class DiscourseGroupsSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'discourse_groups.discourse_groups_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'discourse_groups_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('discourse_groups.discourse_groups_settings');
    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();
    $group_type_options = [];

    foreach ($group_types as $group_type_id => $group_type) {
      $group_type_options[$group_type_id] = $group_type->label();
    }

    $form['group_types_enabled_for_discourse'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Group types for which Discourse Integration should be enabled by default'),
      '#options' => $group_type_options,
      '#default_value' => $config->get('group_types_enabled_for_discourse') == NULL ? [] : $config->get('group_types_enabled_for_discourse'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $group_types_enabled = $form_state->getValue('group_types_enabled_for_discourse');
    $this->config('discourse_groups.discourse_groups_settings')
      ->set('group_types_enabled_for_discourse', $group_types_enabled)
      ->save();
  }

}
