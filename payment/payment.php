<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare("SELECT b.booking_id, b.renter_id, b.total_amount, b.booking_status, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.renter_id = ? LIMIT 1");
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || $booking['booking_status'] !== 'Payment Pending') {
    set_flash('danger', 'This booking is not ready for payment.');
    redirect('booking/my_bookings.php');
}

$page_title = 'Demo Payment';
$methods = ['Demo Card', 'Demo UPI', 'Cash on Delivery/Collection'];
$method = $_POST['payment_method'] ?? 'Demo Card';
$outcome = $_POST['demo_outcome'] ?? 'success';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($method, $methods, true)) {
        $errors[] = 'Select a valid payment method.';
    }
    if (!in_array($outcome, ['success', 'failed'], true)) {
        $errors[] = 'Select a valid demo result.';
    }

    if (empty($errors)) {
        $paymentStatus = $outcome === 'success' ? 'Paid' : 'Failed';
        $reference = 'DEMO-' . strtoupper(str_replace([' ', '/'], ['', '-'], $method)) . '-' . date('YmdHis') . '-' . random_int(100, 999);

        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT booking_status, owner_id, item_id FROM bookings WHERE booking_id = ? AND renter_id = ? FOR UPDATE");
            $lock->execute([$bookingId, current_user_id()]);
            $lockedBooking = $lock->fetch();
            if (!$lockedBooking || $lockedBooking['booking_status'] !== 'Payment Pending') {
                throw new RuntimeException('This booking is no longer ready for payment.');
            }

            $payment = $pdo->prepare('INSERT INTO payments (booking_id, user_id, amount, payment_method, transaction_reference, payment_status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $payment->execute([$bookingId, current_user_id(), $booking['total_amount'], $method, $reference, $paymentStatus, $paymentStatus === 'Paid' ? date('Y-m-d H:i:s') : null]);
            $paymentId = (int) $pdo->lastInsertId();

            if ($paymentStatus === 'Paid') {
                $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Confirmed', status_note = 'Payment completed. Rental confirmed.' WHERE booking_id = ?");
                $update->execute([$bookingId]);
                create_notification($pdo, (int) $lockedBooking['owner_id'], 'Payment received for ' . $booking['item_name'] . '. The rental is confirmed.', 'Payment Successful', $bookingId);
                create_notification($pdo, current_user_id(), 'Payment successful. Your booking for ' . $booking['item_name'] . ' is confirmed.', 'Payment Successful', $bookingId);
            } else {
                $update = $pdo->prepare("UPDATE bookings SET status_note = 'Demo payment failed. You can try payment again.' WHERE booking_id = ?");
                $update->execute([$bookingId]);
            }
            $pdo->commit();
            redirect('payment/' . ($paymentStatus === 'Paid' ? 'payment_success.php?id=' : 'payment_failed.php?id=') . $paymentId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The demo payment could not be processed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="h3 mb-1">Demo Payment</h1><p class="text-muted mb-0">Complete payment for <?php echo e($booking['item_name']); ?>.</p></div><div class="card shadow-sm"><div class="card-body p-4">
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="payment-total mb-4"><span>Total payable</span><strong><?php echo e(format_money($booking['total_amount'])); ?></strong></div>
<form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-3"><label class="form-label required" for="payment_method">Payment Method</label><select class="form-select" id="payment_method" name="payment_method"><?php foreach ($methods as $availableMethod) : ?><option value="<?php echo e($availableMethod); ?>" <?php echo $method === $availableMethod ? 'selected' : ''; ?>><?php echo e($availableMethod); ?></option><?php endforeach; ?></select></div><div class="mb-4"><label class="form-label required">Demo Result</label><div class="d-flex flex-wrap gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="demo_outcome" id="success" value="success" <?php echo $outcome === 'success' ? 'checked' : ''; ?>><label class="form-check-label" for="success">Successful payment</label></div><div class="form-check"><input class="form-check-input" type="radio" name="demo_outcome" id="failed" value="failed" <?php echo $outcome === 'failed' ? 'checked' : ''; ?>><label class="form-check-label" for="failed">Failed payment</label></div></div></div><button class="btn btn-brand" type="submit">Process Demo Payment</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form>
</div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
