<?php

namespace Drupal\payone_payment;

class CreditCardControllerTest extends \DrupalUnitTestCase {

  protected function paymentStub() {
    $payment = entity_create('payment', []);
    $payment->pid = 4711;
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'test',
      'amount' => 42,
    ]));
    $s = new \PaymentStatusItem(PAYMENT_STATUS_PENDING, 0, 4711, 12);
    $payment->setStatus($s);
    return $payment;
  }

  public function test_getReference_isConsistent() {
    $p = $this->paymentStub();
    $controller = new CreditCardController();
    $r1 = $controller->generateReference($p);
    $r2 = $controller->generateReference($p);
    $this->assertEqual($r1, $r2);
  }

  public function test_getReference_doesConformToSpecs() {
    $p = $this->paymentStub();
    $controller = new CreditCardController();
    $r = $controller->generateReference($p);
    // Format according to spec version 2.85.
    $this->assertRegExp('/^[0-9a-zA-Z\\.\\-_\\/]{1,20}$/', '4711-12');
  }

}
