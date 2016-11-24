<?php

namespace Drupal\payone_payment;

class CreditCardController extends \PaymentMethodController {
  public $payment_configuration_form_elements_callback = 'payment_forms_payment_form';
  public $payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';

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
  }

  public function paymentForm(\Payment $payment) {
    return new CreditCardForm();
  }

  public function configurationForm(\PaymentMethod $method) {
    return new CreditCardConfigurationForm();
  }

  /**
   * {@inheritdoc}
   */
  function validate(\Payment $payment, \PaymentMethod $payment_method, $strict) {
    parent::validate($payment, $payment_method, $strict);
  }

  public function generateReference(\Payment $payment) {
    entity_save('payment', $payment);
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

    try {
      // These other keys are defined in $response:
      // - status: 'APPROVED'
      // - txid: The payone transaction id.
      // - userid: The payone user id.
      $response = $api->ccAuthorizationRequest($data);
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
    }
    catch (HttpError $e) {
      // TODO: Maybe retry here a few seconds later?
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      $e->log($payment);
    }
    catch (ApiError $e) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
      $e->log($payment);
    }
  }

}
