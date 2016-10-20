<?php

namespace Drupal\payone_payment;

class HttpError extends ApiError {

  public static function fromHttpResponse($result) {
    return new static($result->message, $result->code);
  }

}
