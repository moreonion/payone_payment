<?php

namespace Drupal\payone_payment;

class CreditCardController extends \PaymentMethodController {

  public $controller_data_defaults = [
    'mid' => '',
    'portalid'  => '',
    'aid' => '',
    'api_key' => '',
    'live' => 0,
    'field_map' => [
      'salutation' => ['salutation'],
      'title' => ['title'],
      'firstname' => ['first_name'],
      'lastname' => ['last_name'],
      'company' => ['company'],
      'street' => ['street', 'street_address'],
      'addressaddition' => [],
      'zip' => ['zip_code', 'postcode'],
      'city' => ['city'],
      'state' => ['state'],
      'country' => ['country'],
      'email' => ['email'],
      'telephonenumber' => ['phone_number'],
      'birthday' => ['date_of_birth'],
      'language' => ['language'],
      'vatid' => ['vatid'],
      'gender' => ['gender'],
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

  public function generateReference(\Payment $payment) {
    $status = $payment->getStatus();
    return $payment->pid . '-' . $status->psiid;
  }

  public function execute(\Payment $payment, $api = NULL) {
    if (!$api) {
      $api = Api::fromControllerData($payment->method->controller_data);
    }
    $context = &$payment->contextObj;

    $currency = currency_load($payment->currency_code);
    $data = [
      'clearingtype' => 'cc',
      'reference' => $this->generateReference($payment),
      'amount' => (int) ($payment->totalAmount(TRUE) * $currency->subunits),
      'currency' => $payment->currency_code,
      'pseudocardpan' => $payment->method_data['payone_pseudocardpan'],
    ] + $payment->method_data['personal_data'];
    $response = $api->serverRequest('authorization', $data);

    if ($response['status'] == 'APPROVED') {
      // These other keys are defined in $response:
      // - txid: The payone transaction id.
      // - userid: The payone user id.
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
    }
    else {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      $message = 'API-Error (pid=@pid, pmid=@pmid): @status @message.';
      $variables = array(
        '@status'   => $response['errorcode'],
        '@message'  => $response['errormessage'],
        '@pid'      => $payment->pid,
        '@pmid'     => $payment->method->pmid,
      );
      watchdog('payone_payment', $message, $variables, WATCHDOG_ERROR);
    }
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
        $data['field_map'] = $data['config']['field_map'] + $method->controller->controller_data_defaults['field_map'];
        unset($data['config']);
        $method->controller_data = $data;
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
    $data['config']['field_map'] = $data['field_map'];
    unset($data['field_map']);
    $data['config'] = serialize($data['config']);

    db_insert('payone_payment_controller_data')
      ->fields($data)
      ->execute();
  }

  /**
   * Helper for entity_update().
   */
  public function update($method) {
    $data = $method->controller_data += $this->controller_data_defaults;
    $data['config']['field_map'] = $data['field_map'];
    unset($data['field_map']);
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
    $cd = $form_state['payment_method']->controller_data;
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

  public function configurationFormValidate(array $element, array &$form_state) {
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

    $form_state['payment_method']->controller_data = $cd;
  }

}
