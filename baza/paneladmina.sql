


ALTER TABLE services
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER duration_minutes;


ALTER TABLE employees
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER phone;


CREATE INDEX idx_reservations_date ON reservations (reservation_date);
