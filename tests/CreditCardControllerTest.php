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

  protected function mockApi() {
    return $this->getMock('\\Drupal\\payone_payment\\Api', ['ccAuthorizationRequest'], [
      'mid' => 1,
      'aid' => 1,
      'portalid' => 1,
      'api_key' => 'asdf',
      'live' => FALSE,
    ]);
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
    $this->assertRegExp('/^[0-9a-zA-Z\\.\\-_\\/]{1,20}$/', $r);
  }

  public function test_execute_success() {
    $p = $this->paymentStub();
    $p->method_data = [
      'payone_pseudocardpan' => '123456789',
      'personal_data' => [
        'lastname' => 'Last',
        'country' => 'AT',
      ],
    ];
    $api = $this->mockApi();
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
      ->method('ccAuthorizationRequest')
      ->with($this->equalTo($expected))
      ->will($this->returnValue(['status' => 'APPROVED']));
    $controller->execute($p, $api);

    $this->assertEqual(PAYMENT_STATUS_SUCCESS, $p->getStatus()->status);
  }

  public function test_execute_apiError() {
    $p = $this->paymentStub();
    $p->method_data = [
      'payone_pseudocardpan' => '123456789',
      'personal_data' => [
        'lastname' => 'Last',
        'country' => 'AT',
      ],
    ];
    $api = $this->mockApi();
    $controller = new CreditCardController();
    $exception = $this->getMock('\\Drupal\\payone_payment\\ApiError', ['log'], ['Testerror', 9999]);
    $exception->expects($this->once())->method('log');
    $api->expects($this->once())
      ->method('ccAuthorizationRequest')
      ->will($this->throwException($exception));
    $controller->execute($p, $api);
  }

}
