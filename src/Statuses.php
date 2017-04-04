<?php

namespace Drupal\payone_payment;

/**
 * Define status names for custom payment statuses.
 *
 * @see hook_payment_status_info().
 * @see payone_payment_payment_status_info().
 */
class Statuses {
  const REDIRECTED = 'payone_payment_status_redirected';

}
