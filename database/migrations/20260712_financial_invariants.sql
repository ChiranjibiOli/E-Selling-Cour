-- Financial invariants for instructor earnings and payouts.
-- Review historical data first, then run this migration once against coursehub.

UPDATE site_settings
SET setting_value = '20'
WHERE setting_key = 'admin_commission_rate'
  AND (
      setting_value IS NULL
      OR TRIM(setting_value) = ''
      OR TRIM(setting_value) NOT REGEXP '^[0-9]+([.][0-9]+)?$'
      OR CAST(setting_value AS DECIMAL(10,2)) < 0
      OR CAST(setting_value AS DECIMAL(10,2)) > 100
  );

UPDATE instructor_earnings
SET commission_rate = LEAST(100, GREATEST(0, commission_rate)),
    commission_amount = ROUND(gross_amount * LEAST(100, GREATEST(0, commission_rate)) / 100, 2),
    instructor_amount = ROUND(gross_amount - (gross_amount * LEAST(100, GREATEST(0, commission_rate)) / 100), 2)
WHERE commission_rate < 0
   OR commission_rate > 100
   OR commission_amount <> ROUND(gross_amount * commission_rate / 100, 2)
   OR instructor_amount <> ROUND(gross_amount - (gross_amount * commission_rate / 100), 2);

DROP TRIGGER IF EXISTS instructor_earnings_before_insert;
DROP TRIGGER IF EXISTS instructor_earnings_before_update;

DELIMITER $$

CREATE TRIGGER instructor_earnings_before_insert
BEFORE INSERT ON instructor_earnings
FOR EACH ROW
BEGIN
    IF NEW.gross_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross earning amount cannot be negative';
    END IF;

    IF NEW.commission_rate < 0 OR NEW.commission_rate > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Commission rate must be between 0 and 100';
    END IF;

    SET NEW.commission_amount = ROUND(NEW.gross_amount * NEW.commission_rate / 100, 2);
    SET NEW.instructor_amount = ROUND(NEW.gross_amount - NEW.commission_amount, 2);
END$$

CREATE TRIGGER instructor_earnings_before_update
BEFORE UPDATE ON instructor_earnings
FOR EACH ROW
BEGIN
    IF NEW.gross_amount < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross earning amount cannot be negative';
    END IF;

    IF NEW.commission_rate < 0 OR NEW.commission_rate > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Commission rate must be between 0 and 100';
    END IF;

    SET NEW.commission_amount = ROUND(NEW.gross_amount * NEW.commission_rate / 100, 2);
    SET NEW.instructor_amount = ROUND(NEW.gross_amount - NEW.commission_amount, 2);
END$$

DELIMITER ;
