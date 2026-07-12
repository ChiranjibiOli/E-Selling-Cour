-- Read-only preflight for the 2026-07-12 security migrations.
-- Run this first. Every result set should be empty before applying constraints/triggers.

-- Duplicate payment references would block the unique transaction constraint.
SELECT transaction_id, COUNT(*) AS duplicate_count
FROM payments
WHERE transaction_id IS NOT NULL AND TRIM(transaction_id) <> ''
GROUP BY transaction_id
HAVING COUNT(*) > 1;

-- More than one payout for the same withdrawal request.
SELECT withdrawal_request_id, COUNT(*) AS duplicate_count
FROM payouts
WHERE withdrawal_request_id IS NOT NULL
GROUP BY withdrawal_request_id
HAVING COUNT(*) > 1;

-- More than one direct payout for the same order/payment/instructor.
SELECT order_id, payment_id, instructor_id, payout_source, COUNT(*) AS duplicate_count
FROM payouts
WHERE payout_source = 'direct_order'
GROUP BY order_id, payment_id, instructor_id, payout_source
HAVING COUNT(*) > 1;

-- Payment ownership or amount does not match the order.
SELECT p.id AS payment_id, p.order_id, p.student_id AS payment_student,
       o.student_id AS order_student, p.paid_amount, o.final_amount
FROM payments p
INNER JOIN orders o ON o.id = p.order_id
WHERE p.student_id <> o.student_id
   OR ROUND(p.paid_amount, 2) <> ROUND(o.final_amount, 2);

-- Order item records an instructor who does not own the course.
SELECT oi.id AS order_item_id, oi.course_id, oi.instructor_id AS recorded_instructor,
       c.instructor_id AS course_owner
FROM order_items oi
INNER JOIN courses c ON c.id = oi.course_id
WHERE oi.instructor_id <> c.instructor_id;

-- Order-item money does not reconcile.
SELECT id AS order_item_id, course_price, discount_amount, final_price
FROM order_items
WHERE course_price < 0
   OR discount_amount < 0
   OR final_price < 0
   OR ROUND(final_price + discount_amount, 2) <> ROUND(course_price, 2);

-- Active enrollment is linked to an unpaid/wrong-student/wrong-course order.
SELECT e.id AS enrollment_id, e.student_id, e.course_id, e.order_id,
       o.student_id AS order_student, o.order_status
FROM enrollments e
LEFT JOIN orders o ON o.id = e.order_id
LEFT JOIN order_items oi ON oi.order_id = e.order_id AND oi.course_id = e.course_id
WHERE e.status = 'active'
  AND e.order_id IS NOT NULL
  AND (
      o.id IS NULL
      OR o.student_id <> e.student_id
      OR o.order_status <> 'paid'
      OR oi.id IS NULL
  );

-- Instructor earning math differs from gross amount and commission rate.
SELECT id AS earning_id, gross_amount, commission_rate,
       commission_amount, instructor_amount,
       ROUND(gross_amount * commission_rate / 100, 2) AS expected_commission,
       ROUND(gross_amount - (gross_amount * commission_rate / 100), 2) AS expected_instructor_amount
FROM instructor_earnings
WHERE gross_amount < 0
   OR commission_rate < 0
   OR commission_rate > 100
   OR ROUND(commission_amount, 2) <> ROUND(gross_amount * commission_rate / 100, 2)
   OR ROUND(instructor_amount, 2) <> ROUND(gross_amount - (gross_amount * commission_rate / 100), 2);

-- Payout recorded by a missing/non-active/non-admin account, or non-positive amount.
SELECT p.id AS payout_id, p.paid_by, p.paid_amount,
       u.role AS recorder_role, u.status AS recorder_status
FROM payouts p
LEFT JOIN users u ON u.id = p.paid_by
WHERE p.paid_amount <= 0
   OR u.id IS NULL
   OR u.role <> 'admin'
   OR u.status <> 'active';

-- Withdrawal request total differs from its linked earning total.
SELECT wr.id AS withdrawal_request_id, wr.instructor_id,
       wr.requested_amount,
       COALESCE(SUM(ie.instructor_amount), 0) AS linked_amount
FROM withdrawal_requests wr
LEFT JOIN withdrawal_request_earnings wre ON wre.withdrawal_request_id = wr.id
LEFT JOIN instructor_earnings ie ON ie.id = wre.earning_id
GROUP BY wr.id, wr.instructor_id, wr.requested_amount
HAVING ROUND(wr.requested_amount, 2) <> ROUND(COALESCE(SUM(ie.instructor_amount), 0), 2);
