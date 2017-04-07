<?php

namespace Drupal\payone_payment;

/**
 * Payment controller for Paypal Express Checkout payments via PayOne.
 */
class PaypalECController extends ControllerBase {

  public $payment_configuration_form_elements_callback = 'payment_forms_payment_form';

  /**
   * Sign method and payment ID.
   *
   * This has to be a static method as it is called from menu access callbacks.
   *
   * @param string $method
   *   A name for this method.
   * @param int $pid
   *   The payment ID.
   *
   * @return string
   *   Base-64 encoded key.
   */
  public static function sign($method, $pid) {
    return drupal_hmac_base64("$method:$pid", drupal_get_private_key());
  }

  public function __construct() {
    $this->title = t('PayONE PayPal EC');
  }

  /**
   * Get a payment form object.
   */
  public function paymentForm(\Payment $payment) {
    return new PaypalECForm();
  }

  /**
   * Generate the return URLs.
   *
   * @param int $pid
   *   The payment ID.
   *
   * @return string[]
   *   The three return URLs needed for e-wallet payments.
   */
  protected function getUrls($pid) {
    $o['absolute'] = TRUE;
    $urls = [];
    foreach (['success', 'error', 'back'] as $m) {
      $hash = self::sign($m, $pid);
      $urls[$m . 'url'] = url("payone-payment/$m/{$pid}/$hash", $o);
    }
    return $urls;
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
