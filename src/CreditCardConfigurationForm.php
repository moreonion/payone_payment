<?php

namespace Drupal\payone_payment;

/**
 * Configuration form for the credit card payment method.
 */
class CreditCardConfigurationForm extends PayoneConfigurationForm {

  /**
   * Allow admins to limit the set of card issuers.
   */
  public function form(array $element, array &$form_state, \PaymentMethod $method) {
    $form = parent::form($element, $form_state, $method);
    $cd = $method->controller_data;

    $form['field_map']['#weight'] = 10;
    $form['issuers'] = [
      '#type' => 'checkboxes',
      '#title' => t('Credit card issuers'),
      '#description' => t('Disable credit card issuers here to limit the cards users can use.'),
      '#options' => CreditCardForm::$issuers,
      '#default_value' => $cd['issuers'],
      '#weight' => 1,
    ];

    return $form;
  }

}
