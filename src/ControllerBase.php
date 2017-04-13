<?php

namespace Drupal\payone_payment;

/**
 * Common functionality needed by all PayOne payment controllers.
 *
 * - Controller data defaults.
 * - Method configuration form.
 * - A way to generate a payment reference.
 */
abstract class ControllerBase extends \PaymentMethodController {

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

  public function configurationForm(\PaymentMethod $method) {
    return new PayoneConfigurationForm();
  }

  /**
   * Generate a unique ID for this payment attempt.
   */
  public function generateReference(\Payment $payment) {
    // If needed save the payment to get actual Ids.
    if (!$payment->pid || !$payment->getStatus()->psiid) {
      entity_save('payment', $payment);
    }
    $status = $payment->getStatus();
    return $payment->pid . '-' . $status->psiid;
  }

  /**
   * Set the transaction ID for the current status item.
   *
   * There is currently no prettier way to attach additional data to payment
   * status items. See https://www.drupal.org/node/2867820 for more information.
   *
   * @param \Payment $payment
   *   The payment object.
   * @param int|null $txid
   *   A transaction ID if there is one.
   */
  protected function setTxid(\Payment $payment, $txid) {
    $status_item = $payment->getStatus();
    if (!$status_item->psiid) {
      entity_save('payment', $payment);
    }
    Transaction::create($status_item->psiid, $txid)->save();
  }

}

