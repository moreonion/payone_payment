<?php

namespace Drupal\payone_payment;

/**
 * Syntactic sugar for having a PersonalDataFields object available.
 */
trait PersonalDataForm {

  protected $personalData;

  public function __construct() {
    $this->personalData = new PersonalDataFields();
  }

}
