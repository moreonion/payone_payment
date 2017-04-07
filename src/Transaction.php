<?php

namespace Drupal\payone_payment;

use \Drupal\little_helpers\DB\Model;

/**
 * Model class for a row in {payone_payment_transactions}.
 */
class Transaction extends Model {
  protected static $table = 'payone_payment_transactions';
  protected static $key = ['psiid'];
  protected static $values = ['txid'];
  protected static $serial = FALSE;

  /**
   * Create a new transaction object.
   *
   * @param int $psiid
   *   Payment status item ID.
   * @param int $txid
   *   Transaction ID from PayOne.
   *
   * @return static
   *   The newly created object.
   */
  public static function create($psiid, $txid) {
    return new static(['psiid' => $psiid, 'txid' => $txid]);
  }

  /**
   * Load a single transaction by it's psiid.
   *
   * @param int $psiid
   *   The payment status item ID.
   *
   * @return static
   *   The newly loaded transaction.
   */
  public static function load($psiid) {
    if ($transactions = static::loadMultiple([$psiid])) {
      return $transactions[$psiid];
    }
  }

  /**
   * Load multiple transaction objects by their psiid (if available).
   *
   * @param int[] $psiids
   *   Payment status item IDs to load.
   *
   * @return static[]
   *   The transactions for all psiids that have one. The transactions are keyed
   *   by their psiid.
   */
  public static function loadMultiple(array $psiids) {
    $result = db_select(static::$table, 't')
      ->fields('t')
      ->condition('psiid', $psiids)
      ->execute();
    $items = [];
    foreach ($result as $row) {
      $items[$row->psiid] = new static($row, FALSE);
    }
    return $items;
  }

  /**
   * Send a merge query.
   */
  protected function merge() {
    db_merge(static::$table)
      ->key($this->values(static::$key))
      ->fields($this->values(array_merge(static::$key, static::$values)))
      ->execute();
  }

  /**
   * Save the row to the database.
   */
  public function save() {
    $this->merge();
    $this->new = FALSE;
  }

  /**
   * Delete transactions by their psiids.
   */
  public static function deleteIds(array $psiids) {
    db_delete(static::$table)
      ->condition('psiid', $psiids)
      ->execute();
  }

}
