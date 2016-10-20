<?php

namespace Drupal\payone_payment;

class ApiTest extends \DrupalUnitTestCase {

  protected $creds = [
    'mid' => 1,
    'aid' => 1,
    'portalid' => 1,
    'api_key' => 'asdf',
    'live' => FALSE,
  ];

  protected function stubApi() {
    return $this->getMockBuilder(Api::class)->setMethods(['post'])
      ->setConstructorArgs([
      $this->creds['mid'],
      $this->creds['portalid'],
      $this->creds['api_key'],
      $this->creds['aid'],
      $this->creds['live'],
    ])->getMock();
  }

  public function test_clientParameters() {
    $api = $this->stubApi();
    $r = $api->clientParameters('test', [
      'storecarddata' => 'yes',
      'thiswillnotbesigned' => 'yes',
      'id' => ['test_array'],
    ]);
    $this->assertEqual('11b983d1452c91b09f96c10f65c1331af1afa9e27204a2c93c2e7b0a6eb2d7e85ad0d03076097dae62e5502d582c85b8', $r['hash']);
    $creds = $this->creds;
    unset($creds['api_key']);
    $creds['mode'] = 'test';
    unset($creds['live']);
    $this->assertEqual($creds, array_intersect_key($r, $creds));
  }

  public function test_ccAuthorizationRequest_approved() {
    $api = $this->stubApi();
    $r = (object) [
      'code' => 200,
      'data' => 'status=APPROVED',
    ];
    $expected_post_options = [
      'method' => 'POST',
      'data' => 'request=authorization&responsetype=JSON&mode=test&mid=1&aid=1&portalid=1&encoding=UTF-8&key=912ec803b2ce49e4a541068d495ab570&pseudocardpan=4711',
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
    ];
    $api->expects($this->once())->method('post')
      ->with($this->equalTo('https://api.pay1.de/post-gateway/'), $this->equalTo($expected_post_options))
      ->will($this->returnValue($r));
    $api->ccAuthorizationRequest(['pseudocardpan' => '4711']);
  }

  public function test_serverRequest_HttpError() {
    $api = $this->stubApi();
    $r = (object) [
      'code' => 0,
      'message' => 'php_network_getaddresses: getaddrinfo failed: Name or service not known',
    ];
    $api->expects($this->once())->method('post')
      ->will($this->returnValue($r));
    $this->expectException(HttpError::class);
    $api->serverRequest('test', ['pseudocardpan' => '4711']);
  }

  public function test_ccAuthorizationRequest_ApiError() {
    $api = $this->stubApi();
    $r = (object) [
      'code' => 200,
      'data' => "status=ERROR\nerrorcode=9999\nerrormessage=TestError",
    ];
    $api->expects($this->once())->method('post')
      ->will($this->returnValue($r));
    $this->expectException(ApiError::class);
    $api->ccAuthorizationRequest(['pseudocardpan' => '4711']);
  }

}
