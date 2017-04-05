<?php

namespace Drupal\payone_payment;

use \Drupal\campaignion\CRM\Import\Source\ArraySource;

class PaypalECFormTest extends \DrupalUnitTestCase {

  /**
   * Create a mock payment.
   */
  protected function mockPayment($context_data) {
    $controller = new PaypalECController();
    $p = entity_create('payment', [
      'method' => entity_create('payment_method', [
        'controller' => $controller,
        'controller_data' => $controller->controller_data_defaults,
      ]),
      'contextObj' => new ArraySource($context_data),
    ]);
    return $p;
  }

  public function testForm() {
    // All required fields are set -> fieldset should be hidden.
    $form_state = [];
    $form_obj = new PaypalECForm();
    $payment = $this->mockPayment([
      'country' => 'AT',
      'first_name' => 'Firstname',
      'last_name' => 'Lastname',
    ]);
    $form = $form_obj->form([], $form_state, $payment);
    $this->assertFalse($form['personal_data']['#access']);
  }

}
