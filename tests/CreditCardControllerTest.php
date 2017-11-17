<?php

namespace Drupal\payone_payment;

class CreditCardControllerTest extends \DrupalUnitTestCase {

  protected $methodData = [
    'payone_pseudocardpan' => '123456789',
    'personal_data' => [
      'lastname' => 'Last',
      'country' => 'AT',
    ],
  ];

  protected function paymentStub() {
    $payment = entity_create('payment', [
      'pid' => 4711,
      'currency_code' => 'EUR',
      'method_data' => $this->methodData,
    ]);
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'test',
      'amount' => 42.00,
    ]));
    $payment->method = entity_create('payment_method', []);
    $s = new \PaymentStatusItem(PAYMENT_STATUS_PENDING, 0, 4711, 12);
    $payment->setStatus($s);
    return $payment;
  }

  protected function mockController() {
    $api = $this->createMock(Api::class);
    $controller = new CreditCardController();
    return [$controller, $api];
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
    $p->pid = NULL;
    entity_save('payment', $p);
    list($controller, $api) = $this->mockController();
    $expected = [
      'clearingtype' => 'cc',
      'reference' => $p->pid . '-12',
      'amount' => 4200,
      'currency' => 'EUR',
      'pseudocardpan' => '123456789',
      'lastname' => 'Last',
      'country' => 'AT',
    ];
    $api->expects($this->once())
      ->method('ccAuthorizationRequest')
      ->with(new \PHPUnit_Framework_Constraint_ArraySubset($expected))
      ->will($this->returnValue(['status' => 'APPROVED', 'txid' => 42]));
    $controller->execute($p, $api);

    $status = $p->getStatus();
    $this->assertEqual(PAYMENT_STATUS_SUCCESS, $status->status);
    $t = Transaction::load($status->psiid);
    $this->assertNotEmpty($t);
    $this->assertEqual(42, $t->txid);
    entity_delete('payment', $p->pid);
    $this->assertEmpty(Transaction::load($status->psiid));
  }

  public function test_execute_ApiError() {
    $p = $this->paymentStub();
    list($controller, $api) = $this->mockController();
    $exception = $this->createMock(ApiError::class);
    $exception->expects($this->once())->method('log');
    $api->expects($this->once())
      ->method('ccAuthorizationRequest')
      ->will($this->throwException($exception));
    $controller->execute($p, $api);

    $this->assertEqual(PAYMENT_STATUS_FAILED, $p->getStatus()->status);
  }

  public function test_execute_HttpError() {
    $p = $this->paymentStub();
    list($controller, $api) = $this->mockController();
    $exception = $this->createMock(HttpError::class);
    $exception->expects($this->once())->method('log');
    $api->expects($this->once())
      ->method('ccAuthorizationRequest')
      ->will($this->throwException($exception));
    $controller->execute($p, $api);

    $this->assertEqual(PAYMENT_STATUS_FAILED, $p->getStatus()->status);
  }

}
