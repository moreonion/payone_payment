<?php

namespace Drupal\payone_payment;

/**
 * Payment controller for Paypal Express Checkout payments via PayOne.
 */
class PaypalECController extends ControllerBase {

  public $payment_configuration_form_elements_callback = 'payment_forms_payment_form';

  public function __construct() {
    $this->title = t('PayONE PayPal EC');
  }

  /**
   * Get a payment form object.
   */
  public function paymentForm(\Payment $payment) {
    return new PaypalECForm();
  }

  public function execute(\Payment $payment, $api = NULL) {
    if (!$api) {
      $api = Api::fromControllerData($payment->method->controller_data);
    }
    $context = &$payment->contextObj;

    $currency = currency_load($payment->currency_code);
    $data = [
      'clearingtype' => 'wlt',
      'wallettype' => 'PPE',
      'reference' => $this->generateReference($payment),
      'amount' => (int) ($payment->totalAmount(TRUE) * $currency->subunits),
      'currency' => $payment->currency_code,
    ] + $this->getUrls($payment->pid) + $payment->method_data['personal_data'];

    try {
      // These other keys are defined in $response:
      // - status: 'REDIRECT'
      // - txid: The payone transaction id.
      // - userid: The payone user id.
      $response = $api->wltAuthorizationRequest($data);
      $payment->setStatus(new \PaymentStatusItem(Statuses::REDIRECTED));
      entity_save('payment', $payment);
      $this->setTxid($payment, $response['txid']);
      $context->redirect($response['redirecturl']);
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
