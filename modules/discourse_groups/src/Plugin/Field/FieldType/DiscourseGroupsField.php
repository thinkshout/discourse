<?php

namespace Drupal\discourse_groups\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'discourse_groups_field' field type.
 *
 * @FieldType(
 *   id = "discourse_groups_field",
 *   label = @Translation("Discourse groups field"),
 *   description = @Translation("Discourse groups field settings") * ),
 *   default_widget = "discourse_groups_widget",
 */
class DiscourseGroupsField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['category_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Discourse Category ID'))
      ->setDescription(t('Discourse Category ID'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['group_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Discourse Group ID'))
      ->setDescription(t('Discourse Group ID'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['group_name'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Discourse Group Name'))
      ->setDescription(t('Discourse Group Name'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['push_to_discourse'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Push Group to Discourse'))
      ->setSettings([
        'display_label' => TRUE,
      ]);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'category_id' => [
          'type' => 'varchar',
          'length' => 128,
          'binary' => TRUE,
        ],
        'group_id' => [
          'type' => 'varchar',
          'length' => 128,
          'binary' => TRUE,
        ],
        'group_name' => [
          'type' => 'varchar',
          'length' => 256,
          'binary' => TRUE,
        ],
        'push_to_discourse' => [
          'type' => 'int',
          'length' => 1,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $category_id = $this->get('category_id')->getValue();
    return $category_id === NULL || $category_id === '';
  }

}
