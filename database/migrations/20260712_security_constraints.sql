-- Security and business-logic constraints for payout idempotency.
-- Run once against the coursehub database after checking for historical duplicates.

SET @schema_name = DATABASE();

SET @withdrawal_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'payouts'
      AND index_name = 'payouts_withdrawal_request_unique'
);

SET @withdrawal_sql = IF(
    @withdrawal_index_exists = 0,
    'ALTER TABLE payouts ADD UNIQUE KEY payouts_withdrawal_request_unique (withdrawal_request_id)',
    'SELECT 1'
);

PREPARE withdrawal_stmt FROM @withdrawal_sql;
EXECUTE withdrawal_stmt;
DEALLOCATE PREPARE withdrawal_stmt;

SET @direct_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'payouts'
      AND index_name = 'payouts_direct_order_unique'
);

SET @direct_sql = IF(
    @direct_index_exists = 0,
    'ALTER TABLE payouts ADD UNIQUE KEY payouts_direct_order_unique (order_id, payment_id, instructor_id, payout_source)',
    'SELECT 1'
);

PREPARE direct_stmt FROM @direct_sql;
EXECUTE direct_stmt;
DEALLOCATE PREPARE direct_stmt;

SET @payment_reference_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'payments'
      AND index_name = 'payments_transaction_reference_unique'
);

SET @payment_reference_sql = IF(
    @payment_reference_index_exists = 0,
    'ALTER TABLE payments ADD UNIQUE KEY payments_transaction_reference_unique (transaction_id)',
    'SELECT 1'
);

PREPARE payment_reference_stmt FROM @payment_reference_sql;
EXECUTE payment_reference_stmt;
DEALLOCATE PREPARE payment_reference_stmt;
