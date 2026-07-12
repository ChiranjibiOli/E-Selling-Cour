-- Cross-table business invariants for orders, payments, enrollments and payouts.
-- Run after reviewing historical rows for mismatches.

DROP TRIGGER IF EXISTS payments_before_insert;
DROP TRIGGER IF EXISTS payments_before_update;
DROP TRIGGER IF EXISTS order_items_before_insert;
DROP TRIGGER IF EXISTS enrollments_before_insert;
DROP TRIGGER IF EXISTS payouts_before_insert;

DELIMITER $$

CREATE TRIGGER payments_before_insert
BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE expected_student INT DEFAULT NULL;
    DECLARE expected_amount DECIMAL(12,2) DEFAULT NULL;

    SELECT student_id, final_amount
      INTO expected_student, expected_amount
    FROM orders
    WHERE id = NEW.order_id
    LIMIT 1;

    IF expected_student IS NULL OR NEW.student_id <> expected_student THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment student must match order student';
    END IF;

    IF ROUND(NEW.paid_amount, 2) <> ROUND(expected_amount, 2) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must match order final amount';
    END IF;
END$$

CREATE TRIGGER payments_before_update
BEFORE UPDATE ON payments
FOR EACH ROW
BEGIN
    DECLARE expected_student INT DEFAULT NULL;
    DECLARE expected_amount DECIMAL(12,2) DEFAULT NULL;

    SELECT student_id, final_amount
      INTO expected_student, expected_amount
    FROM orders
    WHERE id = NEW.order_id
    LIMIT 1;

    IF expected_student IS NULL OR NEW.student_id <> expected_student THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment student must match order student';
    END IF;

    IF ROUND(NEW.paid_amount, 2) <> ROUND(expected_amount, 2) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must match order final amount';
    END IF;
END$$

CREATE TRIGGER order_items_before_insert
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
    DECLARE expected_instructor INT DEFAULT NULL;

    SELECT instructor_id
      INTO expected_instructor
    FROM courses
    WHERE id = NEW.course_id
    LIMIT 1;

    IF expected_instructor IS NULL OR NEW.instructor_id <> expected_instructor THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item instructor must own the selected course';
    END IF;

    IF NEW.course_price < 0 OR NEW.discount_amount < 0 OR NEW.final_price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item amounts cannot be negative';
    END IF;

    IF ROUND(NEW.final_price + NEW.discount_amount, 2) <> ROUND(NEW.course_price, 2) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order item final price and discount must reconcile to course price';
    END IF;
END$$

CREATE TRIGGER enrollments_before_insert
BEFORE INSERT ON enrollments
FOR EACH ROW
BEGIN
    DECLARE matching_items INT DEFAULT 0;
    DECLARE paid_order INT DEFAULT 0;

    IF NEW.order_id IS NOT NULL THEN
        SELECT COUNT(*)
          INTO paid_order
        FROM orders
        WHERE id = NEW.order_id
          AND student_id = NEW.student_id
          AND order_status = 'paid';

        SELECT COUNT(*)
          INTO matching_items
        FROM order_items
        WHERE order_id = NEW.order_id
          AND course_id = NEW.course_id;

        IF paid_order <> 1 OR matching_items < 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment requires a paid matching order item';
        END IF;
    END IF;
END$$

CREATE TRIGGER payouts_before_insert
BEFORE INSERT ON payouts
FOR EACH ROW
BEGIN
    DECLARE admin_count INT DEFAULT 0;

    SELECT COUNT(*)
      INTO admin_count
    FROM users
    WHERE id = NEW.paid_by
      AND role = 'admin'
      AND status = 'active';

    IF admin_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payout must be recorded by an active administrator';
    END IF;

    IF NEW.paid_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payout amount must be positive';
    END IF;

    IF NEW.payout_source = 'withdrawal' AND NEW.withdrawal_request_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Withdrawal payout requires a withdrawal request';
    END IF;

    IF NEW.payout_source = 'direct_order'
       AND (NEW.order_id IS NULL OR NEW.payment_id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Direct-order payout requires order and payment IDs';
    END IF;
END$$

DELIMITER ;
