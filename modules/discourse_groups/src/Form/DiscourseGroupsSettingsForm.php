<?php

namespace Drupal\discourse_groups\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DiscourseSettingsForm.
 */
class DiscourseGroupsSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

//  /**
//   * Drupal\discourse\DiscourseApiClient definition.
//   *
//   * @var \Drupal\discourse\DiscourseApiClient
//   */
//  protected $discourseApiClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
//    $instance->discourseApiClient = $container->get('discourse.discourse_api_client');
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

    // Process enabled groups.
    $enabled_groups = FALSE;
    foreach ($group_types_enabled as $group_type) {
      if ($group_type) {
        $enabled_groups = TRUE;
        // Add field config.
        $field_config = sprintf("field.field.group.%s.field_discourse_id", $group_type);
        // Load up field config data.
        $field_config_data = $this->loadConfigFromModule('field.field.group.placeholder.field_discourse_id');
        // Change placeholder values.
        $field_config_data['value']['id'] = str_replace('PLACEHOLDER', $group_type, $field_config_data['value']['id']);
        $field_config_data['value']['bundle'] = $group_type;
        $config = \Drupal::service('config.factory')->getEditable($field_config);
        $config->setData($field_config_data['value']);
        $config->save();

        // @todo Update entity_view config

        // @todo Update entity_form config

      }

      // @todo add logic to process removal of group types.
      // Delete config? Data loss warning?
    }

    // If groups were enabled...
    if ($enabled_groups) {
      // Check for proper field storage config.
      $discourse_id_storage_config = \Drupal::service('config.factory')
        ->getEditable('field.storage.group.field_discourse_id');
      // If missing add it.
      if ($discourse_id_storage_config->isNew()) {
        $discourse_id_storage_config_value = $this->loadConfigFromModule('field.storage.group.field_discourse_id');
        $discourse_id_storage_config->setData($discourse_id_storage_config_value['value']);
        $discourse_id_storage_config->save();
      }
      // Flush caches to make this all available.
      drupal_flush_all_caches();
    }

    // @todo add logic to process removal of group types.
    // Delete config? Data loss warning?
  }

  /**
   * Helper function to load up a yml config file from a module.
   *
   * @param string $configName
   *   The name of the configuration, like node.type.page, with no ".yml".
   *
   * @return array
   *   An array representation of a yml file.
   */
  private function loadConfigFromModule(string $configName) {
    $file = drupal_get_path('module', 'discourse_groups') . '/config/templates/' . $configName . '.yml';
    $raw = file_get_contents($file);
    if (empty($raw)) {
      throw new \RuntimeException(sprintf('Config file not found at %s', $file));
    }
    $value = Yaml::decode($raw);
    if (!is_array($value)) {
      throw new \RuntimeException(sprintf('Invalid YAML file %s', $file));
    }
    return ['value' => $value, 'file' => $file];
  }


}
