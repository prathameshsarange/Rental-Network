SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE DATABASE IF NOT EXISTS rental_network
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rental_network;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS admin_requests;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS `returns`;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS booking_extensions;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  address VARCHAR(255) NOT NULL,
  location_address VARCHAR(255) DEFAULT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  pincode VARCHAR(10) NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  profile_image VARCHAR(255) DEFAULT NULL,
  role ENUM('User', 'Admin') NOT NULL DEFAULT 'User',
  status ENUM('Active', 'Blocked') NOT NULL DEFAULT 'Active',
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status),
  INDEX idx_users_location (city, state, pincode),
  INDEX idx_users_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_requests (
  request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  request_message VARCHAR(500) DEFAULT NULL,
  request_status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  reviewed_by INT UNSIGNED DEFAULT NULL,
  review_note VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_admin_requests_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_admin_requests_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_admin_requests_status (request_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
  item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  brand VARCHAR(100) DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL,
  item_condition ENUM('New', 'Like New', 'Good', 'Fair') NOT NULL DEFAULT 'Good',
  rental_price DECIMAL(10,2) NOT NULL,
  rental_unit ENUM('Daily', 'Hourly', 'Both') NOT NULL DEFAULT 'Daily',
  hourly_price DECIMAL(10,2) DEFAULT NULL,
  security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  image VARCHAR(255) DEFAULT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  location VARCHAR(150) NOT NULL,
  location_address VARCHAR(255) DEFAULT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  pincode VARCHAR(10) NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  status ENUM('Available', 'Unavailable', 'Rented', 'Maintenance') NOT NULL DEFAULT 'Available',
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_owner
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_items_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_items_search (item_name, brand, city),
  INDEX idx_items_category_status (category_id, status),
  INDEX idx_items_price (rental_price),
  INDEX idx_items_rental_unit (rental_unit),
  INDEX idx_items_owner (owner_id),
  INDEX idx_items_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  renter_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  rental_unit ENUM('Daily', 'Hourly') NOT NULL DEFAULT 'Daily',
  start_time TIME DEFAULT NULL,
  end_time TIME DEFAULT NULL,
  total_days INT UNSIGNED NOT NULL,
  total_hours INT UNSIGNED DEFAULT NULL,
  rental_amount DECIMAL(10,2) NOT NULL,
  security_deposit DECIMAL(10,2) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  booking_status ENUM(
    'Pending',
    'Accepted',
    'Rejected',
    'Payment Pending',
    'Confirmed',
    'Active',
    'Return Requested',
    'Returned',
    'Completed',
    'Cancelled'
  ) NOT NULL DEFAULT 'Pending',
  status_note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_item
    FOREIGN KEY (item_id) REFERENCES items(item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_renter
    FOREIGN KEY (renter_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_owner
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_bookings_item_dates (item_id, start_date, end_date),
  INDEX idx_bookings_item_times (item_id, start_date, end_date, start_time, end_time),
  INDEX idx_bookings_status (booking_status),
  INDEX idx_bookings_renter (renter_id),
  INDEX idx_bookings_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_extensions (
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

CREATE TABLE payments (
  payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  extension_id INT UNSIGNED DEFAULT NULL,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('Demo Card', 'Demo UPI', 'Cash on Delivery/Collection') NOT NULL,
  transaction_reference VARCHAR(100) DEFAULT NULL,
  payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
  payment_date DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_payments_extension
    FOREIGN KEY (extension_id) REFERENCES booking_extensions(extension_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_payments_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_payments_status (payment_status),
  INDEX idx_payments_user (user_id),
  INDEX idx_payments_booking (booking_id)
  , INDEX idx_payments_extension (extension_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `returns` (
  return_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL UNIQUE,
  item_id INT UNSIGNED NOT NULL,
  renter_id INT UNSIGNED NOT NULL,
  return_date DATE NOT NULL,
  return_condition ENUM('Good', 'Fair', 'Damaged', 'Lost') NOT NULL DEFAULT 'Good',
  damage_description TEXT DEFAULT NULL,
  late_days INT UNSIGNED NOT NULL DEFAULT 0,
  late_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  return_status ENUM('Not Returned', 'Return Requested', 'Returned', 'Approved', 'Disputed') NOT NULL DEFAULT 'Not Returned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_returns_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_returns_item
    FOREIGN KEY (item_id) REFERENCES items(item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_returns_renter
    FOREIGN KEY (renter_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_returns_status (return_status),
  INDEX idx_returns_item (item_id),
  INDEX idx_returns_renter (renter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  message VARCHAR(255) NOT NULL,
  notification_type VARCHAR(60) NOT NULL,
  related_id INT UNSIGNED DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id, is_read),
  INDEX idx_notifications_type (notification_type),
  UNIQUE INDEX uq_notifications_user_type_related (user_id, notification_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL UNIQUE,
  item_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reviews_item
    FOREIGN KEY (item_id) REFERENCES items(item_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_reviewer
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_owner
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_reviews_item (item_id),
  INDEX idx_reviews_owner (owner_id),
  INDEX idx_reviews_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  setting_label VARCHAR(150) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users
  (user_id, name, email, phone, password, address, city, state, pincode, profile_image, role, status)
VALUES
  (1, 'System Admin', 'admin@rentalnetwork.test', '9876500000', '$2y$10$h67vsWQ4RKsqtso8a.pjB.zXkntOmmOVRyZBVg4yesnwTt5Toy7PC', 'Admin Office, Rental Network', 'Pune', 'Maharashtra', '411001', NULL, 'Admin', 'Active'),
  (2, 'Aarav Sharma', 'aarav@example.com', '9876500001', '$2y$10$3ts/jDGyAamXolVppz4RtORiveSENt/d4rkrEOqHur29pqb9Rg/am', 'Flat 204, Baner Road', 'Pune', 'Maharashtra', '411045', NULL, 'User', 'Active'),
  (3, 'Neha Patil', 'neha@example.com', '9876500002', '$2y$10$3ts/jDGyAamXolVppz4RtORiveSENt/d4rkrEOqHur29pqb9Rg/am', '12 Lake View Apartments', 'Mumbai', 'Maharashtra', '400076', NULL, 'User', 'Active'),
  (4, 'Rohan Mehta', 'rohan@example.com', '9876500003', '$2y$10$3ts/jDGyAamXolVppz4RtORiveSENt/d4rkrEOqHur29pqb9Rg/am', '88 Civil Lines', 'Nagpur', 'Maharashtra', '440001', NULL, 'User', 'Active'),
  (5, 'Priya Nair', 'priya@example.com', '9876500004', '$2y$10$3ts/jDGyAamXolVppz4RtORiveSENt/d4rkrEOqHur29pqb9Rg/am', 'Green Park Society, Wakad', 'Pune', 'Maharashtra', '411057', NULL, 'User', 'Active'),
  (6, 'Karan Singh', 'karan@example.com', '9876500005', '$2y$10$3ts/jDGyAamXolVppz4RtORiveSENt/d4rkrEOqHur29pqb9Rg/am', 'Model Colony', 'Nashik', 'Maharashtra', '422005', NULL, 'User', 'Active');

INSERT INTO categories
  (category_id, category_name, description, status)
VALUES
  (1, 'Electronics', 'General electronic devices and accessories.', 'Active'),
  (2, 'Vehicles', 'Two-wheelers, bicycles, and transport items.', 'Active'),
  (3, 'Cameras', 'DSLR, mirrorless cameras, lenses, and camera gear.', 'Active'),
  (4, 'Laptops', 'Laptops, notebooks, and computer accessories.', 'Active'),
  (5, 'Furniture', 'Tables, chairs, shelves, and household furniture.', 'Active'),
  (6, 'Sports Equipment', 'Outdoor and indoor sports items.', 'Active'),
  (7, 'Tools', 'Home repair, workshop, and power tools.', 'Active'),
  (8, 'Books', 'Academic, fiction, and reference books.', 'Active'),
  (9, 'Gaming', 'Gaming consoles, controllers, and games.', 'Active'),
  (10, 'Party Equipment', 'Speakers, lights, decor, and event supplies.', 'Active'),
  (11, 'Musical Instruments', 'Guitars, keyboards, and music gear.', 'Active'),
  (12, 'Other', 'Items that do not fit another category.', 'Active');

INSERT INTO items
  (item_id, owner_id, category_id, item_name, description, brand, model, item_condition, rental_price, security_deposit, image, quantity, location, city, state, pincode, status, is_approved)
VALUES
  (1, 2, 3, 'Canon EOS 1500D DSLR Camera', 'DSLR camera with kit lens, charger, memory card, and carry bag.', 'Canon', 'EOS 1500D', 'Good', 600.00, 3000.00, 'canon-eos-1500d.jpg', 1, 'Baner, Pune', 'Pune', 'Maharashtra', '411045', 'Available', 1),
  (2, 2, 4, 'MacBook Pro 13 Laptop', 'MacBook Pro suitable for presentations, editing, and development demos.', 'Apple', 'MacBook Pro 13', 'Like New', 1200.00, 8000.00, 'macbook-pro-13.jpg', 1, 'Baner, Pune', 'Pune', 'Maharashtra', '411045', 'Available', 1),
  (3, 2, 9, 'Sony PlayStation 5 Console', 'PS5 console with one controller and two games.', 'Sony', 'PlayStation 5', 'Like New', 800.00, 5000.00, 'sony-ps5.jpg', 1, 'Baner, Pune', 'Pune', 'Maharashtra', '411045', 'Maintenance', 1),
  (4, 3, 2, 'Honda Activa Scooter', 'Well-maintained scooter for local travel with valid documents.', 'Honda', 'Activa 6G', 'Good', 700.00, 5000.00, 'honda-activa.jpg', 1, 'Powai, Mumbai', 'Mumbai', 'Maharashtra', '400076', 'Available', 1),
  (5, 3, 2, 'Trek Mountain Bicycle', 'Mountain bicycle with helmet and lock included.', 'Trek', 'Marlin 5', 'Good', 250.00, 1000.00, 'trek-bicycle.jpg', 1, 'Powai, Mumbai', 'Mumbai', 'Maharashtra', '400076', 'Rented', 1),
  (6, 3, 1, 'Epson HD Projector', 'HD projector suitable for classroom, office, and movie nights.', 'Epson', 'EB-X49', 'Good', 700.00, 3000.00, 'epson-projector.jpg', 1, 'Powai, Mumbai', 'Mumbai', 'Maharashtra', '400076', 'Available', 1),
  (7, 4, 5, 'Folding Table Set', 'Two folding tables and four chairs for small events.', 'Nilkamal', 'Event Set', 'Fair', 180.00, 1000.00, 'folding-table-set.jpg', 2, 'Civil Lines, Nagpur', 'Nagpur', 'Maharashtra', '440001', 'Available', 1),
  (8, 4, 9, 'Nintendo Switch Console', 'Nintendo Switch with dock, controller, and popular games.', 'Nintendo', 'Switch', 'Good', 450.00, 2500.00, 'nintendo-switch.jpg', 1, 'Civil Lines, Nagpur', 'Nagpur', 'Maharashtra', '440001', 'Available', 1),
  (9, 4, 6, 'Complete Cricket Kit', 'Bat, pads, gloves, helmet, and kit bag.', 'SG', 'Club Kit', 'Good', 220.00, 900.00, 'cricket-kit.jpg', 1, 'Civil Lines, Nagpur', 'Nagpur', 'Maharashtra', '440001', 'Available', 1),
  (10, 5, 7, 'Bosch Drill Machine', 'Electric drill with standard bit set for home repair work.', 'Bosch', 'GSB 500 RE', 'Good', 200.00, 800.00, 'bosch-drill.jpg', 1, 'Wakad, Pune', 'Pune', 'Maharashtra', '411057', 'Available', 1),
  (11, 5, 8, 'Engineering Textbook Set', 'Reference books for first-year engineering subjects.', 'Various', 'Semester 1 Set', 'Fair', 80.00, 500.00, 'engineering-books.jpg', 1, 'Wakad, Pune', 'Pune', 'Maharashtra', '411057', 'Available', 1),
  (12, 5, 5, 'Ergonomic Office Chair', 'Adjustable office chair suitable for study or work from home.', 'Featherlite', 'Astro', 'Good', 150.00, 700.00, 'office-chair.jpg', 1, 'Wakad, Pune', 'Pune', 'Maharashtra', '411057', 'Available', 1),
  (13, 6, 11, 'Yamaha Acoustic Guitar', 'Six-string acoustic guitar with soft case and tuner.', 'Yamaha', 'F310', 'Good', 350.00, 1800.00, 'yamaha-guitar.jpg', 1, 'Model Colony, Nashik', 'Nashik', 'Maharashtra', '422005', 'Available', 1),
  (14, 6, 11, 'Casio Musical Keyboard', 'Portable keyboard with adapter for practice and small events.', 'Casio', 'CTK-3500', 'Good', 300.00, 1500.00, 'casio-keyboard.jpg', 1, 'Model Colony, Nashik', 'Nashik', 'Maharashtra', '422005', 'Available', 1),
  (15, 6, 10, 'JBL Party Speaker', 'Bluetooth party speaker with microphone support.', 'JBL', 'PartyBox 110', 'Like New', 500.00, 2000.00, 'jbl-party-speaker.jpg', 1, 'Model Colony, Nashik', 'Nashik', 'Maharashtra', '422005', 'Available', 1);

UPDATE items
SET image = CASE item_id
  WHEN 1 THEN 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=1000&q=80'
  WHEN 2 THEN 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=80'
  WHEN 3 THEN 'https://images.unsplash.com/photo-1607853202273-797f1c22a38e?auto=format&fit=crop&w=1000&q=80'
  WHEN 4 THEN 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=1000&q=80'
  WHEN 5 THEN 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?auto=format&fit=crop&w=1000&q=80'
  WHEN 6 THEN 'https://images.unsplash.com/photo-1626379953822-baec19c3accd?auto=format&fit=crop&w=1000&q=80'
  WHEN 7 THEN 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80'
  WHEN 8 THEN 'https://images.unsplash.com/photo-1578303512597-81e6cc155b3e?auto=format&fit=crop&w=1000&q=80'
  WHEN 9 THEN 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1000&q=80'
  WHEN 10 THEN 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=1000&q=80'
  WHEN 11 THEN 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?auto=format&fit=crop&w=1000&q=80'
  WHEN 12 THEN 'https://images.unsplash.com/photo-1505843490701-5a6c6409ae1b?auto=format&fit=crop&w=1000&q=80'
  WHEN 13 THEN 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=1000&q=80'
  WHEN 14 THEN 'https://images.unsplash.com/photo-1520523839897-bd0b52f945a0?auto=format&fit=crop&w=1000&q=80'
  WHEN 15 THEN 'https://images.unsplash.com/photo-1545454675-3531b543be5d?auto=format&fit=crop&w=1000&q=80'
  ELSE image
END
WHERE item_id BETWEEN 1 AND 15;

INSERT INTO bookings
  (booking_id, item_id, renter_id, owner_id, start_date, end_date, total_days, rental_amount, security_deposit, total_amount, booking_status, status_note, created_at)
VALUES
  (1, 1, 3, 2, '2026-08-10', '2026-08-12', 3, 1800.00, 3000.00, 4800.00, 'Completed', 'Camera returned successfully.', '2026-08-05 10:15:00'),
  (2, 2, 4, 2, '2026-09-10', '2026-09-13', 4, 4800.00, 8000.00, 12800.00, 'Confirmed', 'Payment completed. Rental confirmed.', '2026-09-02 15:30:00'),
  (3, 5, 2, 3, '2026-09-06', '2026-09-08', 3, 750.00, 1000.00, 1750.00, 'Active', 'Item collected by renter.', '2026-09-01 11:20:00'),
  (4, 6, 5, 3, '2026-09-14', '2026-09-15', 2, 1400.00, 3000.00, 4400.00, 'Pending', 'Waiting for owner approval.', '2026-09-04 09:45:00'),
  (5, 8, 6, 4, '2026-08-20', '2026-08-22', 3, 1350.00, 2500.00, 3850.00, 'Completed', 'Console returned with one-day late fee.', '2026-08-15 13:10:00'),
  (6, 10, 3, 5, '2026-09-18', '2026-09-20', 3, 600.00, 800.00, 1400.00, 'Accepted', 'Owner accepted the request. Payment pending.', '2026-09-03 16:00:00'),
  (7, 12, 2, 5, '2026-08-25', '2026-08-27', 3, 450.00, 700.00, 1150.00, 'Return Requested', 'Renter requested return confirmation.', '2026-08-20 12:25:00'),
  (8, 14, 4, 6, '2026-09-01', '2026-09-02', 2, 600.00, 1500.00, 2100.00, 'Payment Pending', 'Payment was started but not completed.', '2026-08-28 18:35:00'),
  (9, 15, 2, 6, '2026-08-01', '2026-08-02', 2, 1000.00, 2000.00, 3000.00, 'Completed', 'Speaker returned in good condition.', '2026-07-27 17:10:00'),
  (10, 4, 5, 3, '2026-09-22', '2026-09-24', 3, 2100.00, 5000.00, 7100.00, 'Rejected', 'Owner rejected due to personal use.', '2026-09-04 19:00:00');

INSERT INTO payments
  (payment_id, booking_id, user_id, amount, payment_method, transaction_reference, payment_status, payment_date)
VALUES
  (1, 1, 3, 4800.00, 'Demo UPI', 'DEMO-UPI-10001', 'Paid', '2026-08-05 10:20:00'),
  (2, 2, 4, 12800.00, 'Demo Card', 'DEMO-CARD-10002', 'Paid', '2026-09-02 15:40:00'),
  (3, 5, 6, 3850.00, 'Cash on Delivery/Collection', 'DEMO-CASH-10003', 'Paid', '2026-08-20 09:00:00'),
  (4, 7, 2, 1150.00, 'Demo UPI', 'DEMO-UPI-10004', 'Paid', '2026-08-20 12:40:00'),
  (5, 8, 4, 2100.00, 'Demo Card', 'DEMO-CARD-PENDING-10005', 'Pending', NULL),
  (6, 9, 2, 3000.00, 'Demo UPI', 'DEMO-UPI-10006', 'Paid', '2026-07-27 17:20:00'),
  (7, 6, 3, 1400.00, 'Demo UPI', 'DEMO-UPI-PENDING-10007', 'Pending', NULL);

INSERT INTO `returns`
  (return_id, booking_id, item_id, renter_id, return_date, return_condition, damage_description, late_days, late_fee, return_status, created_at)
VALUES
  (1, 1, 1, 3, '2026-08-12', 'Good', NULL, 0, 0.00, 'Approved', '2026-08-12 18:00:00'),
  (2, 5, 8, 6, '2026-08-23', 'Good', 'Returned one day late. No physical damage found.', 1, 100.00, 'Approved', '2026-08-23 19:10:00'),
  (3, 7, 12, 2, '2026-08-30', 'Fair', 'Minor dust marks. Owner review pending.', 3, 300.00, 'Return Requested', '2026-08-30 16:45:00'),
  (4, 9, 15, 2, '2026-08-02', 'Good', NULL, 0, 0.00, 'Approved', '2026-08-02 20:30:00');

INSERT INTO reviews
  (review_id, booking_id, item_id, reviewer_id, owner_id, rating, review_text, created_at)
VALUES
  (1, 1, 1, 3, 2, 5, 'Camera was clean and worked perfectly. Pickup was smooth.', '2026-08-13 09:15:00'),
  (2, 5, 8, 6, 4, 4, 'Console was excellent. Owner was helpful with setup instructions.', '2026-08-24 11:25:00'),
  (3, 9, 15, 2, 6, 5, 'Speaker quality was great for our event. Easy return process.', '2026-08-03 10:05:00');

INSERT INTO notifications
  (notification_id, user_id, message, notification_type, related_id, is_read, created_at)
VALUES
  (1, 2, 'New rental request received for MacBook Pro 13 Laptop.', 'New Rental Request', 2, 0, '2026-09-02 15:30:00'),
  (2, 4, 'Your rental request for MacBook Pro 13 Laptop has been accepted.', 'Rental Accepted', 2, 1, '2026-09-02 15:35:00'),
  (3, 4, 'Payment successful. Your booking is confirmed.', 'Payment Successful', 2, 0, '2026-09-02 15:40:00'),
  (4, 3, 'New rental request received for Epson HD Projector.', 'New Rental Request', 4, 0, '2026-09-04 09:45:00'),
  (5, 5, 'Your rental request for Honda Activa Scooter was rejected.', 'Rental Rejected', 10, 0, '2026-09-04 19:10:00'),
  (6, 5, 'Return request received for Ergonomic Office Chair.', 'Return Request', 7, 0, '2026-08-30 16:45:00'),
  (7, 2, 'Your return request for Ergonomic Office Chair has been sent.', 'Return Request', 7, 1, '2026-08-30 16:45:00'),
  (8, 2, 'New rating received for Canon EOS 1500D DSLR Camera.', 'New Rating', 1, 0, '2026-08-13 09:15:00'),
  (9, 6, 'New rating received for JBL Party Speaker.', 'New Rating', 3, 0, '2026-08-03 10:05:00'),
  (10, 3, 'Your Trek Mountain Bicycle rental is active.', 'Booking Confirmed', 3, 1, '2026-09-06 09:00:00'),
  (11, 2, 'Your Trek Mountain Bicycle rental starts soon.', 'Rental Starting Soon', 3, 0, '2026-09-05 09:00:00'),
  (12, 1, 'Welcome to the Rental Network admin dashboard.', 'Admin Message', NULL, 0, '2026-09-05 10:00:00');

INSERT INTO settings
  (setting_key, setting_value, setting_label)
VALUES
  ('site_name', 'Rental Network', 'Website name'),
  ('late_fee_per_day', '100', 'Late fee per day'),
  ('max_upload_size_mb', '2', 'Maximum image upload size in MB'),
  ('support_email', 'support@rentalnetwork.test', 'Support email address');
