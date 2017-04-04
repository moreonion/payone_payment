<?php

namespace Drupal\payone_payment;

use \Drupal\payment_forms\CreditCardForm as _CreditCardForm;

// Needed for country_get_list().
require_once DRUPAL_ROOT . '/includes/locale.inc';

class CreditCardForm extends _CreditCardForm {
  use PersonalDataForm;

  const AJAX_JS = 'https://secure.pay1.de/client-api/js/ajax.js';

  static protected $issuers = array(
    'V' => 'Visa',
    'M' => 'MasterCard',
    'A' => 'American Express',
    'J' => 'JCB',
    'C' => 'Discover',
    'D' => 'Diners Club',
  );
  static protected $cvc_label = array(
    'V' => 'CVV2 (Card Verification Value 2)',
    'A' => 'CID (Card Identification Number)',
    'M' => 'CVC2 (Card Validation Code 2)',
    'J' => 'CSC (Card Security Code)',
    'C' => 'CID (Card Identification Number)',
    'D' => 'CSC (Card Security Code)',
  );

  public function form(array $form, array &$form_state, \Payment $payment) {
    $form = parent::form($form, $form_state, $payment);

    $form['holder'] = [
      '#type' => 'textfield',
      '#title' => t('Card holder'),
      '#weight' => -1,
    ];

    $method = &$payment->method;

    $params = Api::fromControllerData($method->controller_data)
      ->clientParameters('creditcardcheck', ['storecarddata' => 'yes']);
    $settings['payone_payment'][$method->pmid] = $params;
    drupal_add_js($settings, 'setting');
    drupal_add_js(
      drupal_get_path('module', 'payone_payment') . '/payone.js',
      'file'
    );
    drupal_add_js(self::AJAX_JS, 'external');

    $form['payone_pseudocardpan'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => array('payone-pseudocardpan')),
    );

    $form['personal_data'] = $this->personalData->element([], $form_state, $payment);

    $p = &$form['personal_data'];
    if (!empty($p['firstname']['#default_value']) && !empty($p['lastname']['#default_value'])) {
      $form['holder']['#default_value'] = $p['firstname']['#default_value'] . ' ' . $p['lastname']['#default_value'];
    }

    return $form;
  }

  public function validate(array $element, array &$form_state, \Payment $payment) {
    // PayOne takes care of the real validation, client-side.
    $values = &drupal_array_get_nested_value($form_state['values'], $element['#parents']);

    if (!empty($values['credit_card_number']) || !empty($values['secure_code'])) {
      form_error($element, t('Something went wrong while processing the payment. The site administrator was informed. Please try again later.'));
      watchdog('payone_payment', 'Credit card data was sent to server. Perhaps something is broken with the payone JavaScript.', [], WATCHDOG_ALERT);
    }

    if (empty($values['payone_pseudocardpan']) && !empty($payment->method->controller_data['live'])) {
      form_error($element, t('Something went wrong while processing the payment. The site administrator was informed. Please try again later.'));
      watchdog('payone_payment', 'Received an empty pseudocardpan.', [], WATCHDOG_ERROR);
    }

    unset($values['credit_card_number']);
    unset($values['secure_code']);

    $this->personalData->validate($element['personal_data'], $form_state);
    $payment->method_data = $values;

  }

}
