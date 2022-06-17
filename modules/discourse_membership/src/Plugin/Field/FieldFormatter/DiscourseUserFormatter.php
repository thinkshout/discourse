<?php

namespace Drupal\discourse_membership\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'discourse_user_field' formatter.
 *
 * @FieldFormatter(
 *   id = "discourse_user_formatter",
 *   label = @Translation("Discourse User formatter"),
 *   field_types = {
 *     "discourse_user_field"
 *   }
 * )
 */
class DiscourseUserFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display' => 'username',
    ];
  }

  /**
   * Get the display options.
   *
   * @return array
   *   The display options.
   */
  private function getDisplayOptions() {
    return [
      'username' => $this->t('Username (default)'),
      'user_id' => $this->t('User ID'),
      'push_to_discourse' => $this->t('Push User to Discourse'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $options = $this->getDisplayOptions();
    $display = $this->getSetting('display') ?: 'username';

    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#options' => $options,
      '#default_value' => $display,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $options = $this->getDisplayOptions();
    $display = $this->getSetting('display') ?: 'username';

    $summary[] = $options[$display] ?? '';

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $display = $this->getSetting('display') ?: 'username';

    $elements = [];

    foreach ($items as $delta => $item) {
      switch ($display) {
        case 'push_to_discourse':
          $value = $item->push_to_discourse ? 'Yes' : 'No';
          break;

        default:
          $value = $item->{$display} ?? '';
          break;
      }

      if (!$value) {
        $value = $item->username;
      }

      $elements[$delta] = [
        '#markup' => $value,
      ];
    }

    return $elements;
  }

}
