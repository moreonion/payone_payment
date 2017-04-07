<?php

namespace Drupal\payone_payment;

use \Drupal\campaignion\CRM\Import\Source\ArraySource;

class CreditCardFormTest extends \DrupalUnitTestCase {

  /**
   * Create a mock payment.
   */
  protected function mockPayment($context_data) {
    $controller = new CreditCardController();
    $p = entity_create('payment', [
      'method' => entity_create('payment_method', [
        'controller' => $controller,
        'controller_data' => $controller->controller_data_defaults,
      ]),
      'contextObj' => new ArraySource($context_data),
    ]);
    return $p;
  }

  public function testValidate() {
    // Test a valid submission.
    $form_obj = new CreditCardForm();
    $payment = $this->mockPayment([
      'country' => 'AT',
      'first_name' => 'Firstname',
      'last_name' => 'Lastname',
    ]);
    $form_state = ['payment' => $payment];
    $form_state['values']['payone_pseudocardpan'] = 'somepan';
    drupal_form_submit('payment_forms_payment_form', $form_state);
    $errors = form_set_error();
    $this->assertEqual([], $errors);


    $form_state = ['payment' => $payment];
    $form_state['values']['credit_card_number'] = 'leaked_number';
    $form_state['values']['payone_pseudocardpan'] = 'somepan';
    drupal_form_submit('payment_forms_payment_form', $form_state);
    $errors = form_set_error();
    $this->assertNotEmpty($errors);
  }

}
