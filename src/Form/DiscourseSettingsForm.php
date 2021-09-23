<?php

namespace Drupal\discourse\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DiscourseSettingsForm.
 */
class DiscourseSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\discourse\DiscourseApiClient definition.
   *
   * @var \Drupal\discourse\DiscourseApiClient
   */
  protected $discourseApiClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->discourseApiClient = $container->get('discourse.discourse_api_client');
    return $instance;
  }

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

    $form['forum_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forum Link (base domain) to display the above comments'),
      '#description' => $this->t('This will be used as a link text in Discourse Comments Block. Example FORUM.EXAMPLE.ORG'),
      '#maxlength' => 256,
      '#size' => 64,
      '#default_value' => $config->get('forum_link') ? $config->get('forum_link') : '',
      '#required' => TRUE,
    ];

    $form['forum_link_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for Forum Link'),
      '#description' => $this->t('This will be used as a description for Forum link in Discourse Comments Block.'),
      '#maxlength' => 256,
      '#size' => 64,
      '#default_value' => $config->get('forum_link_label') ? $config->get('forum_link_label') : $this->t('COMMENT ON THIS ARTICLE BY CLICKING HERE:'),
      '#required' => TRUE,
    ];

    $form['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache Lifetime (in minutes)'),
      '#size' => 64,
      '#required' => TRUE,
      // Default cache lifetime 60 minutes.
      '#default_value' => $config->get('cache_lifetime') ? $config->get('cache_lifetime') : 60,
    ];

    if (isset($config) && !empty($config)) {
      $base_url = $config->get('base_url_of_discourse');
      if (isset($base_url) && $base_url != '') {
        $categories = $this->discourseApiClient->getCategories();
        if ($categories) {
          $options = [];
          foreach ($categories['category_list']['categories'] as $cat) {
            $options[$cat['id']] = $cat['name'];
          }
          $form['default_category'] = [
            '#type' => 'select',
            '#title' => $this->t('Default category to post to'),
            '#options' => $options,
            '#default_value' => $config->get('default_category'),
          ];
        }
      }
    }

    // Prepare options for content type checkboxes.
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($content_types as $key => $content_type) {
      $options[$key] = $content_type->get('name');
    }

    $form['content_types_enabled_for_discourse'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types for which Discourse Comments Integration should be enabled by default'),
      '#options' => $options,
      '#default_value' => $config->get('content_types_enabled_for_discourse') == NULL ? [] : $config->get('content_types_enabled_for_discourse'),
    ];

//    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();
//    $group_type_options = [];
//
//    foreach ($group_types as $group_type_id => $group_type) {
//      $group_type_options[$group_type_id] = $group_type->label();
//    }
//
//    $form['group_types_enabled_for_discourse'] = [
//      '#type' => 'checkboxes',
//      '#title' => $this->t('Group types for which Discourse Integration should be enabled by default'),
//      '#options' => $group_type_options,
//      '#default_value' => $config->get('group_types_enabled_for_discourse') == NULL ? [] : $config->get('group_types_enabled_for_discourse'),
//    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $content_types_enabled = $form_state->getValue('content_types_enabled_for_discourse');
//    $group_types_enabled = $form_state->getValue('group_types_enabled_for_discourse');
    $this->config('discourse.discourse_settings')
      ->set('base_url_of_discourse', $form_state->getValue('base_url_of_discourse'))
      ->set('forum_link', $form_state->getValue('forum_link'))
      ->set('forum_link_label', $form_state->getValue('forum_link_label'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_user_name', $form_state->getValue('api_user_name'))
      ->set('cache_lifetime', $form_state->getValue('cache_lifetime'))
      ->set('default_category', $form_state->getValue('default_category'))
      ->set('content_types_enabled_for_discourse', $content_types_enabled)
//      ->set('group_types_enabled_for_discourse', $group_types_enabled)
      ->save();

    // Process enabled groups.
//    $enabled_groups = FALSE;
//    foreach ($group_types_enabled as $group_type) {
//      if ($group_type) {
//        $enabled_groups = TRUE;
//        // Add field config.
//        $field_config = sprintf("field.field.group.%s.field_discourse_id", $group_type);
//        // Load up field config data.
//        $field_config_data = $this->loadConfigFromModule('field.field.group.placeholder.field_discourse_id');
//        // Change placeholder values.
//        $field_config_data['value']['id'] = str_replace('PLACEHOLDER', $group_type, $field_config_data['value']['id']);
//        $field_config_data['value']['bundle'] = $group_type;
//        $config = \Drupal::service('config.factory')->getEditable($field_config);
//        $config->setData($field_config_data['value']);
//        $config->save();
//
//        // Update entity_view config
//
//        // Update entity_form config

//      }
//    }

//    // If groups were enabled...
//    if ($enabled_groups) {
//      // Check for proper field storage config.
//      $discourse_id_storage_config = \Drupal::service('config.factory')
//        ->getEditable('field.storage.group.field_discourse_id');
//      // If missing add it.
//      if ($discourse_id_storage_config->isNew()) {
//        $discourse_id_storage_config_value = $this->loadConfigFromModule('field.storage.group.field_discourse_id');
//        $discourse_id_storage_config->setData($discourse_id_storage_config_value['value']);
//        $discourse_id_storage_config->save();
//      }
//      // Flush caches to make this all available.
//      drupal_flush_all_caches();
//    }
//
//    // @todo add logic to process removal of group types.
//    // Delete config? Data loss warning?
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
    $file = drupal_get_path('module', 'discourse') . '/config/optional/' . $configName . '.yml';
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
