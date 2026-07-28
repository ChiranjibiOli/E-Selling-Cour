-- Backfill lifetime enrollment records for verified paid order items that were created before enrollment finalization was reliable.
-- Refunded enrollments remain refunded; paid revoked records are restored to active lifetime access.
INSERT INTO enrollments (
    student_id,
    course_id,
    order_id,
    payment_id,
    access_type,
    status,
    granted_by,
    granted_at
)
SELECT
    o.student_id,
    oi.course_id,
    o.id,
    p.id,
    'lifetime',
    'active',
    p.verified_by,
    COALESCE(p.verified_at, NOW())
FROM orders o
INNER JOIN payments p
    ON p.order_id = o.id
    AND p.student_id = o.student_id
INNER JOIN order_items oi
    ON oi.order_id = o.id
WHERE o.order_status = 'paid'
  AND p.payment_status = 'paid'
ON DUPLICATE KEY UPDATE
    order_id = VALUES(order_id),
    payment_id = VALUES(payment_id),
    access_type = 'lifetime',
    status = IF(status = 'refunded', 'refunded', 'active'),
    granted_by = COALESCE(VALUES(granted_by), granted_by),
    revoked_by_admin = IF(status = 'refunded', revoked_by_admin, NULL),
    revoked_at = IF(status = 'refunded', revoked_at, NULL);
