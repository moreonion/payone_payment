<?php

namespace \Drupal\payone_payment;

class CreditCardConfigurationForm implements \Drupal\payment_forms\MethodFormInterface {
  
  public function configurationForm(array $element, array &$form_state, \PaymentMethod $method) {
    $cd = $method->controller_data;
    $cd += $this->controller_data_defaults;
    $cd['field_map'] += $this->controller_data_defaults['field_map'];

    $element['credentials'] = [
      '#type' => 'fieldset',
      '#title' => t('API access'),
      '#description' => t("You can create, view and edit the API access using the PayOne Merchant Interface (Configuration - Payment Portals)."),
    ];

    $element['credentials']['mid'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant ID'),
      '#description' => t('The Merchant ID is a 5 to 6 digit number.'),
      '#required' => TRUE,
      '#default_value' => $cd['mid'],
    ];

    $element['credentials']['portalid'] = [
      '#type' => 'textfield',
      '#title' => t('Portal ID'),
      '#description' => t('The Portal ID is a 7 digit number.'),
      '#required' => TRUE,
      '#default_value' => $cd['portalid'],
    ];

    $element['credentials']['aid'] = [
      '#type' => 'textfield',
      '#title' => t('Account ID'),
      '#description' => t('The Account ID is a 6 digit number.'),
      '#required' => TRUE,
      '#default_value' => $cd['aid'],
    ];

    $element['credentials']['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Key'),
      '#required' => true,
      '#default_value' => $cd['api_key'],
    ];

    $element['credentials']['live'] = [
      '#type' => 'radios',
      '#title' => 'Mode',
      '#options' => [
        'test' => t('Test mode'),
        'live' => t('Live'),
      ],
      '#default_value' => !empty($cd['live']) ? 'live' : 'test',
    ];

    $element['field_map'] = array(
      '#type' => 'fieldset',
      '#title' => t('Personal data mapping'),
      '#description' => t('This setting allows you to map data from the payment context to payone fields. If data is found for one of the mapped fields it will be transferred to payone. Use a comma to separate multiple field keys.'),
    );

    $map = $cd['field_map'];
    foreach (CreditCardForm::extraDataFields() as $name => $field) {
      $default = implode(', ', isset($map[$name]) ? $map[$name] : array());
      $element['field_map'][$name] = array(
        '#type' => 'textfield',
        '#title' => $field['#title'],
        '#default_value' => $default,
      );
    }

    return $element;
  }

  private function validateNumeric(&$value, $min, $max) {
    $value = preg_replace('/\\s+/', '', $value);
    return preg_match("/^[0-9]{{$min},{$max}}$/", $value);
  }

  public function configurationFormValidate(array $element, array &$form_state, \PaymentMethod $method) {
    $cd = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    foreach ($cd['field_map'] as $k => &$v) {
      $v = array_filter(array_map('trim', explode(',', $v)));
    }

    if (!$this->validateNumeric($cd['credentials']['mid'], 5, 6)) {
      form_error($element['credentials']['mid'], t('Please enter a valid Merchant ID.'));
    }
    if (!$this->validateNumeric($cd['credentials']['portalid'], 7, 7)) {
      form_error($element['credentials']['portalid'], t('Please enter a valid Portal ID. It must be a 7 digit number.'));
    }

    if (!$this->validateNumeric($cd['credentials']['aid'], 0, 6)) {
      form_error($element['credentials']['aid'], t('Please enter a valid Account ID. It must be a number with up to 6 digits.'));
    }

    $cd += $cd['credentials'];
    unset($cd['credentials']);

    $cd['live'] = $cd['live'] == 'live' ? 1 : 0;
    // Trim accidentally copy & pasted spaces.
    $cd['api_key'] = trim($cd['api_key']);

    // TODO: Make a test API call to verify the configuration.

    $method->controller_data = $cd;
  }

  
}

