-- Adds optional hourly rental support while keeping all existing listings daily.
ALTER TABLE items
  ADD COLUMN rental_unit ENUM('Daily', 'Hourly', 'Both') NOT NULL DEFAULT 'Daily' AFTER rental_price,
  ADD COLUMN hourly_price DECIMAL(10,2) DEFAULT NULL AFTER rental_unit,
  ADD INDEX idx_items_rental_unit (rental_unit);

ALTER TABLE bookings
  ADD COLUMN rental_unit ENUM('Daily', 'Hourly') NOT NULL DEFAULT 'Daily' AFTER end_date,
  ADD COLUMN start_time TIME DEFAULT NULL AFTER rental_unit,
  ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time,
  ADD COLUMN total_hours INT UNSIGNED DEFAULT NULL AFTER total_days,
  ADD INDEX idx_bookings_item_times (item_id, start_date, end_date, start_time, end_time);
