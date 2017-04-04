<?php

namespace Drupal\payone_payment;

use \Drupal\payment_forms\PaymentFormInterface;

/**
 * Payment form element for Paypal Express Checkout payments.
 */
class PaypalECForm implements PaymentFormInterface {
  use PersonalDataForm;

  /**
   * Get a form element for this payment method.
   */
  public function form(array $form, array &$form_state, \Payment $payment) {
    $form['redirection_info']['#theme'] = 'payone_payment_redirect_info';
    $form['personal_data'] = $this->personalData->element([], $form_state, $payment);
    return $form;
  }

  /**
   * Validate values submitted for this payment element.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    $values = &drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $this->personalData->validate($element['personal_data'], $form_state);
    $payment->method_data = $values;
  }

}
