<?php

namespace Drupal\discourse_groups\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\discourse\DiscourseApiClient;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'discourse_groups_widget' widget.
 *
 * @FieldWidget(
 *   id = "discourse_groups_widget",
 *   module = "discourse_groups",
 *   label = @Translation("Discourse Groups widget"),
 *   field_types = {
 *     "discourse_groups_field"
 *   }
 * )
 */
class DiscourseGroupsWidget extends WidgetBase {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;
  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ConfigFactory $config_factory, RouteMatchInterface $route_match) {
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('discourse.discourse_api_client'), $container->get('config.factory'), $container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $group = $this->routeMatch->getParameter('group');
    $group_bundle = $this->routeMatch->getParameter('group_type');
    $group_type = NULL;
    if ($group_bundle != NULL) {
      $group_type = $group_bundle->id();
    }
    if ($group instanceof Group) {
      $group_type = $group->bundle();
    }

    $discourse_groups_config = $this->configFactory->get('discourse_groups.discourse_groups_settings');
    $group_types_enabled_for_discourse = $discourse_groups_config->get('group_types_enabled_for_discourse');
    $default_group_type_setting = 0;
    if (isset($group_types_enabled_for_discourse[$group_type]) && $group_types_enabled_for_discourse[$group_type]) {
      $default_group_type_setting = 1;
    }

    $element['push_to_discourse'] = [
      '#title' => $this->t('Push group to Discourse'),
      '#description' => $this->t('NOTE: Disabling this after the group is
        pushed to Discourse will not remove the group from Discourse.'),
      '#type' => 'checkbox',
      '#default_value' => isset($items[$delta]->push_to_discourse) ? $items[$delta]->push_to_discourse : $default_group_type_setting,
    ];

    $element['category_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Discourse Category ID'),
      '#default_value' => isset($items[$delta]->category_id) ? $items[$delta]->category_id : NULL,
      '#size' => 5,
      '#disabled' => TRUE,
    ];

    $element['group_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Discourse Group Name'),
      '#default_value' => isset($items[$delta]->group_name) ? $items[$delta]->group_name : NULL,
      '#size' => 60,
      '#placeholder' => '',
      '#maxlength' => 256,
      '#disabled' => TRUE,
    ];

    $element['group_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Discourse Group ID'),
      '#default_value' => isset($items[$delta]->group_id) ? $items[$delta]->group_id : NULL,
      '#size' => 5,
      '#disabled' => TRUE,
    ];

    $element += [
      '#type' => 'details',
      '#group' => 'advanced',
      '#weight' => 0,
    ];

    return $element;
  }

}
