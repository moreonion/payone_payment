<?php

namespace Drupal\payone_payment;

/**
 * Test CRUD operations for the transaction object.
 */
class TransactionTest extends \DrupalUnitTestCase {

  /**
   * Test CRUD operations.
   */
  public function testCreateSaveLoadDelete() {
    $tc = Transaction::create(42, 42);
    $tc->save();
    $tl = Transaction::load(42);
    // Test whether loaded transaction is the same.
    $this->assertEqual($tc, $tl);

    Transaction::deleteIds([$tl->psiid]);
    // Loading yields an empty result after deletion.
    $this->assertEmpty(Transaction::load(42));
  }

}
