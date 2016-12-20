<?php

namespace Drupal\payone_payment;

// Needed for country_get_list().
require_once DRUPAL_ROOT . '/includes/locale.inc';

class CreditCardForm extends \Drupal\payment_forms\CreditCardForm {
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

    $form['personal_data'] = [
      '#type' => 'fieldset',
      '#title' => t('Personal data'),
      '#weight' => 100,
    ] + $this->mappedFields($payment);

    // Only display the fieldset if there is fields in it.
    $all_done = TRUE;
    foreach (element_children($form['personal_data'], FALSE) as $c) {
      $e = $form['personal_data'][$c];
      if (!isset($e['#access']) || $e['#access']) {
        $all_done = FALSE;
        break;
      }
    }
    $form['personal_data']['#access'] = !$all_done;

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

    $p = &$values['personal_data'];

    if (empty($p['country'])) {
      form_error($element['personal_data']['country'], t('Please select your country.'));
    }

    if (empty($p['firstname']) && empty($p['company'])) {
      form_error($element['personal_data'], t('Either a first name or a company name must be given.'));
    }
    // Only pass non-empty values to the API.
    foreach ($p as $k => $v) {
      if (!$v) {
        unset($p[$k]);
      }
    }

    $payment->method_data = $values;

  }

  protected function mappedFields(\Payment $payment) {
    $fields = static::extraDataFields();
    $field_map = $payment->method->controller_data['field_map'];
    foreach ($fields as $name => &$field) {
      $map = isset($field_map[$name]) ? $field_map[$name] : array();
      $has_value = FALSE;
      foreach ($map as $key) {
        if ($value = $payment->contextObj->value($key)) {
          if (!isset($field['#options']) || isset($field['#options'][$value])) {
            $field['#default_value'] = $value;
            $has_value = TRUE;
            break;
          }
        }
      }
      if (empty($field['#required']) || $has_value) {
        $field['#access'] = FALSE;
      }
      // We can't have required fields in optional parts of the form.
      // This payment method might not be the only way to pay. ;)
      $field['#required'] = FALSE;
    }

    // Show firstname and company fields if both are empty.
    if (empty($fields['firstname']['#default_value']) && empty($fields['company']['#default_value'])) {
      unset($field['firstname']['#access']);
      unset($field['company']['#access']);
    }

    return $fields;
  }

  public static function extraDataFields() {
    $fields = array();
    $f = array(
      'salutation' => t('Salutation'),
      'title' => t('Title'),
      'firstname' => t('First name'),
      'lastname' => t('Last name'),
      'company' => t('Company'),
      'street' => t('Street number and name'),
      'addressaddition' => t('Address line 2'),
      'zip' => t('Postal code'),
      'city' => t('City'),
      'state' => t('State'),
      'country' => t('Country'),
      'email' => t('Email'),
      'telephonenumber' => t('Phone number'),
      'birthday' => t('Day of birth'),
      'language' => t('Language'),
      'vatid' => t('VAT identification'),
      'gender' => t('Gender'),
    );
    foreach ($f as $name => $title) {
      $fields[$name] = array(
        '#type' => 'textfield',
        '#title' => $title,
      );
    }

    $fields['lastname']['#required'] = TRUE;
    $fields['country']['#type'] = 'select';
    $fields['country']['#options'] = country_get_list();
    $fields['country']['#required'] = TRUE;
    $fields['gender']['#type'] = 'radios';
    $fields['gender']['#options'] = ['f' => t('female'), 'm' => t('male')];


    return $fields;
  }

}
