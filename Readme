# Rental Network

A peer-to-peer rental marketplace where people list items they own and rent items from others. Built with plain PHP, MySQL and Bootstrap. It runs on a local XAMPP setup.

**Short description:** Rental Network lets users rent out cameras, laptops, tools, vehicles and more. It handles the full rental cycle: listing, location-based discovery, booking with availability checks, (demo) payment, extensions, returns with late fees, reviews, and an admin panel.

---

## Features

### For users
- **Accounts:** register, log in, edit profile with photo, change password, forgot/reset password (token expires in 30 minutes).
- **Listings:** add, edit and delete items with an image, category, condition, price and security deposit.
- **Rental modes:** daily, hourly, or both.
- **Browse and search:** filter items by category, price and city.
- **Map and location:** pick an item location on a map (Leaflet + OpenStreetMap). Public pages show only approximate coordinates, so the exact pin stays private.
- **Nearby alerts:** users with a saved location get a notification when a new item is listed within 10 km (Haversine distance).
- **Booking:** request an item for a date range or an hourly slot. The system blocks overlapping bookings.
- **Booking flow:** `Pending → Accepted → Payment Pending → Confirmed → Active → Return Requested → Returned → Completed`. Rejected and Cancelled are also supported.
- **Payment:** demo payment (card, UPI, cash) with a payment history page. No real money moves.
- **Extensions:** renter asks for a later end date, owner approves, renter pays the extra amount, and the booking updates only after payment.
- **Returns:** renter requests return, owner records condition (Good / Fair / Damaged / Lost). Late fee is added automatically (default ₹100 per day).
- **Reviews:** renters rate and review an item after a completed booking.
- **Notifications:** in-app alerts for booking updates, payments, nearby items, and rental reminders (day before, due day, overdue).

### For admins
- Dashboard with platform counts.
- Manage users (block / unblock), categories, items (approve / hide), bookings, payments, returns, reviews and notifications.
- Review admin-role requests from users.
- Site settings: site name, late fee per day, max upload size, support email.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ (procedural), PDO |
| Database | MySQL / MariaDB (InnoDB, foreign keys) |
| Frontend | HTML, Bootstrap 5.3, custom CSS, vanilla JavaScript |
| Maps | Leaflet 1.9 + OpenStreetMap, Nominatim for address search |
| Local server | XAMPP (Apache + MySQL) |

Bootstrap, Leaflet and Google Fonts load from CDNs, so you need an internet connection for styling and maps.

---

## Project structure

```
RentalNetwork/
├── index.php, login.php, register.php, dashboard.php, profile.php ...
├── admin/            # Admin panel pages
├── booking/          # Book, accept, reject, cancel, extend, availability check
├── items/            # Add, edit, delete, browse, details, nearby items
├── payment/          # Demo payment, success, failed, history, extension payment
├── returns/          # Request and manage returns
├── reviews/          # Add and view reviews
├── notifications/    # Notification list
├── categories/       # Public category list
├── includes/         # db.php, functions.php, auth.php, header/navbar/footer, location_config.php
├── assets/           # css/style.css, js/script.js, js/location-picker.js
├── tasks/            # send_rental_reminders.php (daily job)
├── uploads/          # profile_images/, item_images/ (user uploads)
└── database/         # rental_network.sql + migration scripts + feature guide
```

---

## Database

11 tables: `users`, `admin_requests`, `categories`, `items`, `bookings`, `booking_extensions`, `payments`, `returns`, `notifications`, `reviews`, `settings`.

Key design points:
- Foreign keys everywhere, with `RESTRICT` on records that must not vanish (items, bookings) and `CASCADE` on dependent rows (notifications, payments).
- A unique index on `notifications (user_id, notification_type, related_id)` prevents duplicate alerts.
- One review and one return per booking (`UNIQUE` on `booking_id`).
- Money is stored as `DECIMAL(10,2)`, coordinates as `DECIMAL(10,7)`.

---

## Setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy the `RentalNetwork` folder into `C:\xampp\htdocs\`.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`) and import `database/rental_network.sql`. It creates the `rental_network` database and sample data.
   - **Warning:** this file drops and recreates all tables. Do not run it on a database you want to keep.
4. Check the database settings in `includes/db.php` (defaults: host `localhost`, user `root`, empty password, database `rental_network`).
5. If you use a different folder name, change `BASE_URL` in `includes/functions.php` (default `/RentalNetwork/`).
6. Make sure `uploads/profile_images/` and `uploads/item_images/` are writable.
7. Open `http://localhost/RentalNetwork/`.

### Existing database? Run the migrations

If you set up the database before these features existed, run these once in phpMyAdmin:

| File | Adds |
|---|---|
| `database/location_feature_migration.sql` | Location fields and nearby alerts |
| `database/hourly_rental_migration.sql` | Hourly rentals |
| `database/booking_extension_migration.sql` | Booking extensions |

### Daily rental reminders

Reminders are also checked on page loads. For reliable delivery, schedule this once a day:

```
C:\xampp\php\php.exe C:\xampp\htdocs\RentalNetwork\tasks\send_rental_reminders.php
```

Use Windows Task Scheduler, or cron on Linux.

---

## Demo accounts

The seed data includes an admin (`admin@rentalnetwork.test`) and five users (`aarav@example.com`, `neha@example.com`, `rohan@example.com`, `priya@example.com`, `karan@example.com`).

> TODO: add the seed password here. Passwords are stored as bcrypt hashes in the SQL file, so it cannot be read from the code. Change it before any real use.

---

## Configuration

| What | Where |
|---|---|
| Nearby radius (km) | `NEARBY_RADIUS_KM` in `includes/location_config.php` |
| Late fee per day, upload size, site name, support email | Admin panel → Settings |
| Database credentials | `includes/db.php` |
| Base URL | `BASE_URL` in `includes/functions.php` |

---

## Security notes

Done:
- Passwords hashed with `password_hash`.
- PDO prepared statements (no string-built SQL with user input).
- Output escaped with `htmlspecialchars` (the `e()` helper).
- Image uploads checked for size, extension and real MIME type, and saved under random names.
- Reset tokens are stored hashed and expire.
- Booking and payment steps use database transactions with `SELECT ... FOR UPDATE` to reduce double-booking.

Known limitations (be honest about these in interviews):
- **No CSRF tokens** on forms.
- **Payments are demo only.** There is no real payment gateway.
- **Password reset shows the link on screen** instead of emailing it. Replace with real email (e.g. PHPMailer) before production.
- Database runs as `root` with no password in the default config.
- No automated tests.

---

## Future improvements

- Real payment gateway (Razorpay / Stripe) with webhook verification.
- Email notifications and real password-reset emails.
- CSRF protection, rate limiting on login.
- In-app chat between owner and renter.
- Automated tests and a Docker setup.

---

## Author

Prathamesh Sarange — 3rd-year CSE student, Sipna College of Engineering and Technology, Amravati.