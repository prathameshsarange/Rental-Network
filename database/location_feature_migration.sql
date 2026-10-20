-- Run once on the existing rental_network database. This does not delete any data.
ALTER TABLE users
    ADD COLUMN location_address VARCHAR(255) NULL AFTER address,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER pincode,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD INDEX idx_users_coordinates (latitude, longitude);

ALTER TABLE items
    ADD COLUMN location_address VARCHAR(255) NULL AFTER location,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER pincode,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD INDEX idx_items_coordinates (latitude, longitude);

-- Allows the existing notification table to enforce one nearby-item alert per user and item.
ALTER TABLE notifications
    ADD UNIQUE INDEX uq_notifications_user_type_related (user_id, notification_type, related_id);
