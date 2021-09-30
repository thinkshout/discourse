<?php

namespace Drupal\discourse_membership\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'discourse_user_widget' widget.
 *
 * @FieldWidget(
 *   id = "discourse_user_widget",
 *   module = "discourse_user",
 *   label = @Translation("Discourse User widget"),
 *   field_types = {
 *     "discourse_user_field"
 *   }
 * )
 */
class DiscourseUserWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['push_to_discourse'] = [
      '#title' => $this->t('Push user to Discourse'),
      '#description' => $this->t('NOTE: Disabling this after the user is
        pushed to Discourse will not remove the user from Discourse.'),
      '#type' => 'checkbox',
      '#default_value' => isset($items[$delta]->push_to_discourse) ? $items[$delta]->push_to_discourse : TRUE,
    ];

    $element['user_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Discourse User ID'),
      '#default_value' => isset($items[$delta]->user_id) ? $items[$delta]->user_id : NULL,
      '#size' => 5,
      '#disabled' => TRUE,
    ];

    $element['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Discourse Username'),
      '#default_value' => isset($items[$delta]->username) ? $items[$delta]->username : NULL,
      '#size' => 60,
      '#placeholder' => '',
      '#maxlength' => 256,
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
