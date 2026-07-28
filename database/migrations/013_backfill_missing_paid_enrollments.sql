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
  AND NOT EXISTS (
      SELECT 1
      FROM enrollments e
      WHERE e.student_id = o.student_id
        AND e.course_id = oi.course_id
  );

UPDATE enrollments e
INNER JOIN payments p
    ON p.id = e.payment_id
    AND p.student_id = e.student_id
INNER JOIN orders o
    ON o.id = e.order_id
    AND o.student_id = e.student_id
SET
    e.status = 'active',
    e.access_type = 'lifetime',
    e.revoked_by_admin = NULL,
    e.revoked_at = NULL
WHERE e.status = 'revoked'
  AND p.payment_status = 'paid'
  AND o.order_status = 'paid';
