<?php

namespace Drupal\payone_payment;

class Api {

  const SERVER_API_ENDPOINT = 'https://api.pay1.de/post-gateway/';
  const API_VERSION = 3.10;

  const HASH_KEYS = [
    'access_aboperiod',
    'access_aboprice',
    'access_canceltime',
    'accesscode',
    'access_expiretime',
    'accessname',
    'access_period',
    'access_price',
    'access_starttime',
    'access_vat',
    'addresschecktype',
    'aid',
    'amount',
    'amount_recurring',
    'amount_trail',
    'api_version',
    'backurl',
    'booking_date',
    'checktype',
    'clearingtype',
    'consumerscoretype',
    'currency',
    'customerid',
    'de_recurring[x]',
    'de_trail[x]',
    'de[x]',
    'document_date',
    'due_time',
    'eci',
    'ecommercemode',
    'encoding',
    'errorurl',
    'exiturl',
    'getusertoken',
    'id_recurring[x]',
    'id_trail[x]',
    'id[x]',
    'invoiceappendix',
    'invoice_deliverydate',
    'invoice_deliveryenddate',
    'invoice_deliverymode',
    'invoiceid',
    'it[x]',
    'mandate_identification',
    'mid',
    'mode',
    'narrative_text',
    'no_recurring[x]',
    'no_trail[x]',
    'no[x]',
    'param',
    'period_length_recurring',
    'period_length_trail',
    'period_unit_recurring',
    'period_unit_trail',
    'portalid',
    'productid',
    'pr_recurring[x]',
    'pr_trail[x]',
    'pr[x]',
    'reference',
    'request',
    'responsetype',
    'settleaccount',
    'settleperiod',
    'settletime',
    'storecarddata',
    'successurl',
    'ti_recurring[x]',
    'ti_trail[x]',
    'ti[x]',
    'userid',
    'vaccountname',
    'va_recurring[x]',
    'va_trail[x]',
    'va[x]',
    'vreference',
  ];

  protected $mid;
  protected $portalid;
  protected $key;
  protected $aid;
  protected $live;

  public static function fromControllerData($cd) {
    return new static($cd['mid'], $cd['portalid'], $cd['api_key'], $cd['aid'], $cd['live']);
  }

  public function __construct($mid, $portalid, $key, $aid, $live) {
    $this->mid = $mid;
    $this->portalid = $portalid;
    $this->key = $key;
    $this->aid = $aid;
    $this->live = !empty($live);
  }

  public function authParams($request, $server = FALSE) {
    $params = [
      'request' => $request,
      'responsetype' => 'JSON',
      'mode' => $this->live ? 'live' : 'test',
      'mid' => $this->mid,
      'aid' => $this->aid,
      'portalid' => $this->portalid,
      'encoding' => 'UTF-8',
    ];
    if ($server) {
      $params['key'] = md5($this->key);
    }
    return $params;
  }

  public function clientParameters($request, $data = []) {
    $params = $this->authParams($request) + $data;
    $params['hash'] = $this->hmacSign($params);
    return $params;
  }

  public function serverRequest($request, $data) {
    $params = $this->authParams($request, TRUE) + $data;
    $post_data = http_build_query($params);
    $r = drupal_http_request('https://api.pay1.de/post-gateway/', [
      'method' => 'POST',
      'data' => $post_data,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
    ]);

    // Parse the response - which consists of lines of key=value pairs.
    $response = [];
    foreach (explode("\n", $r->data) as $line) {
      if ($line) {
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
          $response[$parts[0]] = $parts[1];
        }
      }
    }
    // @TODO Throw exceptions on API-Error.

    return $response;
  }

  protected function hmacSign($data) {
    $hash_string = '';

    foreach (self::HASH_KEYS as $k) {
      if (substr($k, -3) == '[x]') {
        $k = substr($k, 0, -3);
        if (isset($data[$k]) && is_array($data[$k])) {
          ksort($data[$k]);
          $hash_string .= implode(array_values($data[$k]));
        }
      }
      else {
        if (isset($data[$k])) {
          $hash_string .= (string) $data[$k];
        }
      }
    }

    return hash_hmac('sha384', $hash_string, $this->key);
  }

}
