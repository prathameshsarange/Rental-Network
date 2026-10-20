<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$bookingId = (int) ($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare('SELECT b.booking_id, b.owner_id, b.booking_status, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.renter_id = ? LIMIT 1');
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || !in_array($booking['booking_status'], ['Pending', 'Accepted', 'Payment Pending'], true)) {
    set_flash('danger', 'This booking cannot be cancelled.');
    redirect('booking/my_bookings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', status_note = 'Cancelled by renter.' WHERE booking_id = ? AND booking_status IN ('Pending', 'Accepted', 'Payment Pending')");
    $update->execute([$bookingId]);
    if ($update->rowCount() === 1) {
        create_notification($pdo, (int) $booking['owner_id'], 'The rental request for ' . $booking['item_name'] . ' was cancelled by the renter.', 'Booking Cancelled', $bookingId);
        set_flash('success', 'Booking cancelled successfully.');
    } else {
        set_flash('warning', 'This booking has already been updated.');
    }
    redirect('booking/booking_details.php?id=' . $bookingId);
}

$page_title = 'Cancel Booking';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4">Cancel Booking</h1><p class="text-muted">Cancel your rental request for <?php echo e($booking['item_name']); ?>?</p><form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><button class="btn btn-danger" type="submit">Yes, Cancel Booking</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Keep Booking</a></form></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
