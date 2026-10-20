-- Run once on the existing rental_network database. No existing records are removed.
CREATE TABLE IF NOT EXISTS booking_extensions (
  extension_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  renter_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  previous_end_date DATE NOT NULL,
  requested_end_date DATE NOT NULL,
  additional_days INT UNSIGNED NOT NULL,
  additional_amount DECIMAL(10,2) NOT NULL,
  extension_status ENUM('Pending', 'Payment Pending', 'Paid', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
  renter_note VARCHAR(500) DEFAULT NULL,
  owner_note VARCHAR(500) DEFAULT NULL,
  payment_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_booking_extensions_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_booking_extensions_renter FOREIGN KEY (renter_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_booking_extensions_owner FOREIGN KEY (owner_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_booking_extensions_booking_status (booking_id, extension_status),
  INDEX idx_booking_extensions_owner_status (owner_id, extension_status),
  INDEX idx_booking_extensions_renter (renter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments ADD COLUMN extension_id INT UNSIGNED NULL AFTER booking_id;
ALTER TABLE payments ADD INDEX idx_payments_extension (extension_id);
ALTER TABLE payments ADD CONSTRAINT fk_payments_extension FOREIGN KEY (extension_id) REFERENCES booking_extensions(extension_id) ON UPDATE CASCADE ON DELETE SET NULL;
