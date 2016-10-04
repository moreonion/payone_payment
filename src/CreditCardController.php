<?php

namespace Drupal\payone_payment;

class CreditCardController extends \PaymentMethodController {
  const SERVER_API_ENDPOINT = 'https://api.pay1.de/post-gateway/';
  const API_VERSION = 3.10;

  public $controller_data_defaults = [
    'mid' => '',
    'portalid'  => '',
    'api_key' => '',
    'live' => 0,
    'config' => [
      'field_map' => [],
    ],
  ];

  public function __construct() {
    $this->title = t('PayONE Credit Card');
    $this->form = new CreditCardForm();

    $this->payment_configuration_form_elements_callback = 'payment_forms_method_form';
    $this->payment_method_configuration_form_elements_callback = 'payone_payment_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  function validate(\Payment $payment, \PaymentMethod $payment_method, $strict) {
    parent::validate($payment, $payment_method, $strict);
  }

  public function execute(\Payment $payment) {
    $context = &$payment->contextObj;
  }

  /**
   * Helper for entity_load().
   */
  public static function load($entities) {
    $pmids = array();
    foreach ($entities as $method) {
      if ($method->controller instanceof CreditCardController) {
        $pmids[] = $method->pmid;
      }
    }
    if ($pmids) {
      $result = db_select('payone_payment_controller_data', 'c')
        ->fields('c')
        ->condition('pmid', $pmids)
        ->execute();
      while ($data = $result->fetchAssoc()) {
        $method = $entities[$data['pmid']];
        unset($data['pmid']);
        $data['config'] = unserialize($data['config']);
        $method->controller_data = (array) $data;
        $method->controller_data += $method->controller->controller_data_defaults;
      }
    }
  }

  /**
   * Helper for entity_insert().
   */
  public function insert($method) {
    $method->controller_data += $this->controller_data_defaults;
    $data = $method->controller_data;
    $data['pmid'] = $method->pmid;
    $data['config'] = serialize($data['config']);

    db_insert('payone_payment_controller_data')
      ->fields($data)
      ->execute();
  }

  /**
   * Helper for entity_update().
   */
  public function update($method) {
    $data = $method->controller_data;
    $data['config'] = serialize($data['config']);
    db_update('payone_payment_controller_data')
      ->fields($data)
      ->condition('pmid', $method->pmid)
      ->execute();
  }

  /**
   * Helper for entity_delete().
   */
  public function delete($method) {
    db_delete('payone_payment_controller_data')
      ->condition('pmid', $method->pmid)
      ->execute();
  }

  public function configurationForm(array $element, array &$form_state) {
    $cd = drupal_array_merge_deep($this->controller_data_defaults, $form_state['payment_method']->controller_data);

    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => t('API access'),
      '#description' => t("You can create, view and edit the API access using the PayOne Merchant Interface (Configuration - Payment Portals)."),
    ];

    $form['credentials']['mid'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant ID'),
      '#description' => t('The Merchant ID is a 5 to 6 digit number.'),
      '#required' => TRUE,
      '#default_value' => $cd['mid'],
    ];

    $form['credentials']['portalid'] = [
      '#type' => 'textfield',
      '#title' => t('Portal ID'),
      '#description' => t('The Portal ID is a 7 digit number.'),
      '#required' => TRUE,
      '#default_value' => $cd['portalid'],
    ];

    $form['credentials']['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Key'),
      '#required' => true,
      '#default_value' => $cd['api_key'],
    ];

    $form['credentials']['live'] = [
      '#type' => 'radios',
      '#title' => 'Mode',
      '#options' => [
        'test' => t('Test mode'),
        'live' => t('Live'),
      ],
      '#default_value' => $cd['live'] ? 'live' : 'test',
    ];

    $form['config']['field_map'] = array(
      '#type' => 'fieldset',
      '#title' => t('Personal data mapping'),
      '#description' => t('This setting allows you to map data from the payment context to payone fields. If data is found for one of the mapped fields it will be transferred to payone. Use a comma to separate multiple field keys.'),
    );

    $map = $cd['config']['field_map'];
    foreach (CreditCardForm::extraDataFields() as $name => $field) {
      $default = implode(', ', isset($map[$name]) ? $map[$name] : array());
      $form['config']['field_map'][$name] = array(
        '#type' => 'textfield',
        '#title' => $field['#title'],
        '#default_value' => $default,
      );
    }

    return $form;
  }

  private function validateNumeric(&$value, $min, $max) {
    $value = preg_replace('/\\s+/', '', $value);
    return preg_match("/^[0-9]{{$min},{$max}}$/", $value);
  }

  public function configurationFormValidate(array $element, array &$form_state) {
    $cd = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    foreach ($cd['config']['field_map'] as $k => &$v) {
      $v = array_filter(array_map('trim', explode(',', $v)));
    }

    if (!$this->validateNumeric($cd['credentials']['mid'], 5, 6)) {
      form_error($element['credentials']['mid'], t('Please enter a valid Merchant ID.'));
    }
    if (!$this->validateNumeric($cd['credentials']['portalid'], 7, 7)) {
      form_error($element['credentials']['portalid'], t('Please enter a valid Portal ID. It must be a 7 digit number.'));
    }


    $cd += $cd['credentials'];
    unset($cd['credentials']);

    $cd['live'] = $cd['live'] == 'live' ? 1 : 0;
    // Trim accidentally copy & pasted spaces.
    $cd['api_key'] = trim($cd['api_key']);

    // TODO: Make a test API call to verify the configuration.

    $form_state['payment_method']->controller_data = $cd;
  }

}
