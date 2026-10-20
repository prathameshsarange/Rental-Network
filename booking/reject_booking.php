<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$bookingId = (int) ($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare('SELECT b.booking_id, b.renter_id, b.booking_status, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.owner_id = ? LIMIT 1');
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || $booking['booking_status'] !== 'Pending') {
    set_flash('danger', 'This booking request cannot be rejected.');
    redirect('booking/received_bookings.php');
}

$page_title = 'Reject Booking';
$reason = trim($_POST['reason'] ?? '');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($reason === '') { $errors[] = 'Please provide a reason for rejecting this request.'; }
    if (empty($errors)) {
        $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Rejected', status_note = ? WHERE booking_id = ? AND booking_status = 'Pending'");
        $update->execute([$reason, $bookingId]);
        if ($update->rowCount() === 1) {
            create_notification($pdo, (int) $booking['renter_id'], 'Your rental request for ' . $booking['item_name'] . ' was rejected. ' . $reason, 'Rental Rejected', $bookingId);
            set_flash('success', 'Booking request rejected.');
            redirect('booking/booking_details.php?id=' . $bookingId);
        }
        $errors[] = 'This booking request has already been updated.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4 mb-2">Reject Booking Request</h1><p class="text-muted">Tell the renter why <?php echo e($booking['item_name']); ?> cannot be rented for these dates.</p><?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?><form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-3"><label class="form-label required" for="reason">Reason</label><textarea class="form-control" id="reason" name="reason" rows="4" required><?php echo e($reason); ?></textarea></div><button class="btn btn-danger" type="submit">Reject Request</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
