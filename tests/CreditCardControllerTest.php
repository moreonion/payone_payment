<?php

namespace Drupal\payone_payment;

class CreditCardControllerTest extends \DrupalUnitTestCase {

  protected function paymentStub() {
    $payment = entity_create('payment', [
      'pid' => 4711,
      'currency_code' => 'EUR',
    ]);
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'test',
      'amount' => 42.00,
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

  public function test_execute_data() {
    $p = $this->paymentStub();
    $p->method_data = [
      'payone_pseudocardpan' => '123456789',
      'personal_data' => [
        'lastname' => 'Last',
        'country' => 'AT',
      ],
    ];
    $api = $this->getMock('\\Drupal\\payone_payment\\Api', ['serverRequest'], [
      'mid' => 1,
      'aid' => 1,
      'portalid' => 1,
      'api_key' => 'asdf',
      'live' => FALSE,
    ]);
    $controller = new CreditCardController();
    $expected = [
      'clearingtype' => 'cc',
      'reference' => '4711-12',
      'amount' => 4200,
      'currency' => 'EUR',
      'pseudocardpan' => '123456789',
      'lastname' => 'Last',
      'country' => 'AT',
    ];
    $api->expects($this->once())
      ->method('serverRequest')
      ->with($this->equalTo('authorization'), $this->equalTo($expected))
      ->will($this->returnValue(['status' => 'APPROVED']));
    $controller->execute($p, $api);
  }

}
