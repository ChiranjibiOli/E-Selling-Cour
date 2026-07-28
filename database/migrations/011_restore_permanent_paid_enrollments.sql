-- Restore permanent access for verified purchases that were left revoked by the removed access-removal workflow.
UPDATE enrollments e
INNER JOIN payments p
    ON p.id = e.payment_id
    AND p.student_id = e.student_id
INNER JOIN orders o
    ON o.id = e.order_id
    AND o.student_id = e.student_id
INNER JOIN order_items oi
    ON oi.order_id = e.order_id
    AND oi.course_id = e.course_id
SET
    e.status = 'active',
    e.access_type = 'lifetime',
    e.revoked_by_admin = NULL,
    e.revoked_at = NULL
WHERE e.status = 'revoked'
  AND p.payment_status = 'paid'
  AND o.order_status = 'paid';
