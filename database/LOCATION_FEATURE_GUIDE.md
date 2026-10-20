# Location Mapping and Nearby Alerts

## Existing architecture used

- PHP/PDO database connection: `includes/db.php`
- Session authentication and notification helper: `includes/functions.php`
- Existing tables: `users`, `items`, and `notifications`
- Existing item pages: `items/add_item.php`, `items/edit_item.php`, and `items/item_details.php`

No separate location or notification table was created. Existing text location fields remain unchanged.

## Database migration

For an existing database, run `database/location_feature_migration.sql` once in phpMyAdmin. It adds nullable `location_address`, `latitude`, and `longitude` fields to `users` and `items`, plus an index that prevents duplicate nearby-item notifications.

The active local database was migrated during implementation.

## Configuration

Change `NEARBY_RADIUS_KM` in `includes/location_config.php` to set the default notification radius. The default is `10.0` KM.

## How it works

1. Users save an optional private location through **Profile > Edit Profile**.
2. Item owners must choose a pin while creating or editing a listing.
3. The browser sends a Nominatim request only after an explicit search, pin placement, or current-location action. It never searches while the user types.
4. When a new item is created, PHP calculates distances with the Haversine formula and notifies saved-location users within the configured radius.
5. The owner is excluded. Users without saved coordinates are excluded. Existing notifications are checked and protected by a unique index.
6. Product pages use rounded coordinates for their public map marker, so the exact pin is not exposed.

## Test checklist

1. Sign in and open `edit_profile.php`; use the map to save a user location.
2. Sign in as another user; open `items/add_item.php` and select a map pin.
3. Create the item near the saved user location.
4. Open Notifications for the nearby user. A `Nearby Item` alert should appear and open the item page when clicked.
5. Change the profile location to more than 10 KM away, create another item, and confirm no alert is created.
6. Deny browser location permission and confirm that map click and Search continue to work.

## Rental return reminders

The notification system also sends the renter one reminder on the day before the end date, one on the return due date, and one overdue alert after the end date. The overdue alert includes the currently configured late fee.

For fully automatic local reminders, create a Windows Task Scheduler task that runs once daily:

`C:\xampp\php\php.exe D:\projects\RentalNetwork\tasks\send_rental_reminders.php`

As a fallback, reminders are also checked whenever a website page is opened. Duplicate alerts are prevented by the existing notification uniqueness rule.

## Rental extensions

Run `database/booking_extension_migration.sql` once in phpMyAdmin for an existing database. The active local database was migrated during implementation.

1. The renter opens **My Bookings** and then **View Details** for a current or future `Confirmed` or `Active` booking.
2. Select **Request Extension**, choose a new end date, and submit the request.
3. The owner opens the booking from their notification or **Rental Requests**, then selects **Review Extension**.
4. If the owner approves, the renter receives an extra-payment notification.
5. The renter selects **Pay Extension Amount** and completes a successful demo payment.
6. The booking end date, total days, rental amount, and total amount update only after successful payment.

The extension request is rejected if the requested period conflicts with another booking. Overdue bookings cannot be extended because they must follow the late-return flow.
# Hourly Rental Test

1. Open **My Items**, edit any item, and select **Per Hour** or **Daily and Hourly** under Rental Mode.
2. Enter an hourly price, for example `₹100`, then save the item.
3. From another account, open the item and choose **Hourly rental**.
4. Select one date, `10:00` as start time, and `13:00` as end time. The estimate should show `3 hour(s)` and `₹300` rental amount, plus the security deposit.
5. Send the request and accept it from the owner account.
6. Try to request the same item from `11:00` to `12:00`. The system must mark the slot unavailable.
7. Try `14:00` to `16:00`. This non-overlapping slot should be available.
