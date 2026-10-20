<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('booking/received_bookings.php');
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$statement = $pdo->prepare('SELECT b.*, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.owner_id = ? LIMIT 1');
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || $booking['booking_status'] !== 'Pending') {
    set_flash('danger', 'This booking request cannot be accepted.');
    redirect('booking/received_bookings.php');
}

try {
    $pdo->beginTransaction();
    $hasConflict = $booking['rental_unit'] === 'Hourly'
        ? has_hourly_booking_conflict($pdo, (int) $booking['item_id'], $booking['start_date'], $booking['start_time'], $booking['end_time'], $bookingId)
        : has_booking_conflict($pdo, (int) $booking['item_id'], $booking['start_date'], $booking['end_date'], $bookingId);
    if ($hasConflict) {
        throw new RuntimeException('Another booking already covers this rental period. This request cannot be accepted.');
    }
    $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Payment Pending', status_note = 'Owner accepted the request. Payment is pending.' WHERE booking_id = ? AND booking_status = 'Pending'");
    $update->execute([$bookingId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('This booking request has already been updated.');
    }
    create_notification($pdo, (int) $booking['renter_id'], 'Your rental request for ' . $booking['item_name'] . ' has been accepted. Payment is pending.', 'Rental Accepted', $bookingId);
    $pdo->commit();
    set_flash('success', 'Booking accepted. The renter can now proceed to payment.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'The booking could not be accepted. Please try again.');
}

redirect('booking/booking_details.php?id=' . $bookingId);
