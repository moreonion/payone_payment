<?php

namespace Drupal\payone_payment;

class CreditCardForm extends \Drupal\payment_forms\CreditCardForm {
  const CLIENT_API_ENDPOINT = 'https://secure.pay1.de/client-api/';

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

  public function getForm(array &$form, array &$form_state, \Payment $payment) {
    parent::getForm($form, $form_state, $payment);

    $form['holder'] = [
      '#type' => 'textfield',
      '#title' => t('Card holder'),
      '#weight' => -1,
    ];

    $method = &$payment->method;

    $params = $method->controller->parameters('creditcardcheck', $method->controller_data);
    $settings['payone_payment'][$method->pmid] = $params;
    drupal_add_js($settings, 'setting');
    drupal_add_js(
      drupal_get_path('module', 'payone_payment') . '/payone.js',
      'file'
    );
    drupal_add_js('https://secure.pay1.de/client-api/js/ajax.js', 'external');

    $form['payone_pseudocardpan'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => array('payone-pseudocardpan')),
    );
  }

  public function validateForm(array &$element, array &$form_state, \Payment $payment) {
    // Stripe takes care of the real validation, client-side.
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $payment->method_data['payone_payment_token'] = $values['payone_payment_token'];
  }

  protected function mappedFields(\Payment $payment) {
    $fields = array();
    $field_map = $payment->method->controller_data['config']['field_map'];
    foreach (static::extraDataFields() as $name => $field) {
      $map = isset($field_map[$name]) ? $field_map[$name] : array();
      foreach ($map as $key) {
        if ($value = $payment->contextObj->value($key)) {
          $field['#value'] = $value;
          $fields[$name] = $field;
        }
      }
    }
    return $fields;
  }

  public static function extraDataFields() {
    $fields = array();
    $f = array(
      'name' => t('Name'),
      'first_name' => t('First name'),
      'last_name' => t('Last name'),
      'address_line1' => t('Address line 1'),
      'address_line2' => t('Address line 2'),
      'address_city' => t('City'),
      'address_state' => t('State'),
      'address_zip' => t('Postal code'),
      'address_country' => t('Country'),
    );
    foreach ($f as $name => $title) {
      $fields[$name] = array(
        '#type' => 'hidden',
        '#title' => $title,
        '#attributes' => array('data-payone' => $name),
      );
    }
    return $fields;
  }
}
