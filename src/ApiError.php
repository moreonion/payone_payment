<?php

namespace Drupal\payone_payment;

class ApiError extends \Exception {

  public static function fromResponseData(array $data) {
    return new static($data['errormessage'], $data['errorcode']);
  }

  public function log(\Payment $payment) {
    $message = 'API-Error (pid=@pid, pmid=@pmid): @status @message.';
    $variables = array(
      '@status'   => $this->code,
      '@message'  => $this->message,
      '@pid'      => $payment->pid,
      '@pmid'     => $payment->method->pmid,
    );
    watchdog_exception('payone_payment', $this, $message, $variables, WATCHDOG_ERROR);
  }

}
