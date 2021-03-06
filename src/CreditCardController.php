<?php

namespace Drupal\payone_payment;

class CreditCardController extends ControllerBase {
  public $payment_configuration_form_elements_callback = 'payment_forms_payment_form';

  public function __construct() {
    $this->title = t('PayONE Credit Card');
    $k = array_keys(CreditCardForm::$issuers);
    $this->controller_data_defaults += [
      'issuers' => array_combine($k, $k),
    ];
  }

  /**
   * Get a new method configuration form object.
   */
  public function configurationForm(\PaymentMethod $method) {
    return new CreditCardConfigurationForm();
  }

  public function paymentForm(\Payment $payment) {
    return new CreditCardForm();
  }

  public function execute(\Payment $payment, $api = NULL) {
    if (!$api) {
      $api = Api::fromControllerData($payment->method->controller_data);
    }
    $context = &$payment->contextObj;

    $currency = currency_load($payment->currency_code);
    $urls = $this->getUrls($payment->pid);
    unset($urls['backurl']);
    $data = [
      'clearingtype' => 'cc',
      'reference' => $this->generateReference($payment),
      'amount' => (int) ($payment->totalAmount(TRUE) * $currency->subunits),
      'currency' => $payment->currency_code,
      'pseudocardpan' => $payment->method_data['payone_pseudocardpan'],
    ] + $urls + $payment->method_data['personal_data'];

    try {
      $response = $api->ccAuthorizationRequest($data);
      switch ($response['status']) {
        case 'APPROVED':
          // These other keys are defined in $response:
          // - status: 'APPROVED'
          // - txid: The payone transaction id.
          // - userid: The payone user id.
          $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
          $this->setTxid($payment, $response['txid']);
          break;
        case 'REDIRECT':
          // These other keys are defined in $response:
          // - status: 'REDIRECT'
          // - txid: The payone transaction id.
          // - userid: The payone user id.
          // - redirecturl: The URL to redirect the user to.
          $payment->setStatus(new \PaymentStatusItem(Statuses::REDIRECTED));
          $this->setTxid($payment, $response['txid']);
          $context->redirect($response['redirecturl']);
          break;
      }
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
