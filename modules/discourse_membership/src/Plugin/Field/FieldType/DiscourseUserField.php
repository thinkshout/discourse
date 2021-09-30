<?php

namespace Drupal\discourse_membership\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'discourse_user_field' field type.
 *
 * @FieldType(
 *   id = "discourse_user_field",
 *   label = @Translation("Discourse groups field"),
 *   description = @Translation("Discourse groups field settings") * ),
 *   default_widget = "discourse_user_widget",
 */
class DiscourseUserField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['user_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Discourse User ID'))
      ->setDescription(t('Discourse User ID'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['username'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Discourse username'))
      ->setDescription(t('Discourse username'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(FALSE);

    $properties['push_to_discourse'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Push User to Discourse'))
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
        'user_id' => [
          'type' => 'varchar',
          'length' => 128,
          'binary' => TRUE,
        ],
        'username' => [
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
    $user_id = $this->get('user_id')->getValue();
    return $user_id === NULL || $user_id === '';
  }

}
