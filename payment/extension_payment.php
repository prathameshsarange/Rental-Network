<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$extensionStatement = $pdo->prepare("SELECT e.*, b.booking_status, b.end_date, b.total_days, b.rental_amount, b.total_amount, i.item_name FROM booking_extensions e INNER JOIN bookings b ON b.booking_id = e.booking_id INNER JOIN items i ON i.item_id = b.item_id WHERE e.booking_id = ? AND e.renter_id = ? AND e.extension_status = 'Payment Pending' ORDER BY e.created_at DESC LIMIT 1");
$extensionStatement->execute([$bookingId, current_user_id()]);
$extension = $extensionStatement->fetch();

if (!$extension) {
    set_flash('danger', 'There is no approved extension waiting for payment.');
    redirect('booking/my_bookings.php');
}

$page_title = 'Extension Payment';
$methods = ['Demo Card', 'Demo UPI', 'Cash on Delivery/Collection'];
$method = $_POST['payment_method'] ?? 'Demo Card';
$outcome = $_POST['demo_outcome'] ?? 'success';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($method, $methods, true)) { $errors[] = 'Select a valid payment method.'; }
    if (!in_array($outcome, ['success', 'failed'], true)) { $errors[] = 'Select a valid demo result.'; }

    if (!$errors) {
        $paymentStatus = $outcome === 'success' ? 'Paid' : 'Failed';
        $reference = 'DEMO-EXT-' . date('YmdHis') . '-' . random_int(100, 999);
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT e.*, b.booking_status, b.end_date, b.total_days, b.rental_amount, b.total_amount, b.owner_id FROM booking_extensions e INNER JOIN bookings b ON b.booking_id = e.booking_id WHERE e.extension_id = ? AND e.renter_id = ? FOR UPDATE");
            $lock->execute([$extension['extension_id'], current_user_id()]);
            $locked = $lock->fetch();
            if (!$locked || $locked['extension_status'] !== 'Payment Pending') { throw new RuntimeException('This extension is no longer ready for payment.'); }
            $payment = $pdo->prepare('INSERT INTO payments (booking_id, extension_id, user_id, amount, payment_method, transaction_reference, payment_status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $payment->execute([$bookingId, $locked['extension_id'], current_user_id(), $locked['additional_amount'], $method, $reference, $paymentStatus, $paymentStatus === 'Paid' ? date('Y-m-d H:i:s') : null]);
            $paymentId = (int) $pdo->lastInsertId();

            if ($paymentStatus === 'Paid') {
                $updateExtension = $pdo->prepare("UPDATE booking_extensions SET extension_status = 'Paid', payment_id = ?, paid_at = NOW() WHERE extension_id = ? AND extension_status = 'Payment Pending'");
                $updateExtension->execute([$paymentId, $locked['extension_id']]);
                $updateBooking = $pdo->prepare("UPDATE bookings SET end_date = ?, total_days = ?, rental_amount = ?, total_amount = ?, status_note = 'Extension payment completed. Rental end date updated.' WHERE booking_id = ?");
                $updateBooking->execute([$locked['requested_end_date'], (int) $locked['total_days'] + (int) $locked['additional_days'], (float) $locked['rental_amount'] + (float) $locked['additional_amount'], (float) $locked['total_amount'] + (float) $locked['additional_amount'], $bookingId]);
                create_notification($pdo, (int) $locked['owner_id'], 'Extension payment received for ' . $extension['item_name'] . '. The rental end date was updated.', 'Extension Payment Successful', (int) $locked['extension_id']);
                create_notification($pdo, current_user_id(), 'Extension payment successful. Your new rental end date is ' . date('d M Y', strtotime($locked['requested_end_date'])) . '.', 'Extension Payment Successful', (int) $locked['extension_id']);
            }
            $pdo->commit();
            if ($paymentStatus === 'Paid') { set_flash('success', 'Extension payment successful. Your rental period was updated.'); redirect('booking/booking_details.php?id=' . $bookingId); }
            set_flash('danger', 'Demo payment failed. You can try again.');
            redirect('payment/extension_payment.php?booking_id=' . $bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The extension payment could not be processed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="h3 mb-1">Pay for Rental Extension</h1><p class="text-muted mb-0">Extra payment for <?php echo e($extension['item_name']); ?>.</p></div><div class="card shadow-sm"><div class="card-body p-4">
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="payment-total mb-4"><span>Extra rental period: <?php echo e((string) $extension['additional_days']); ?> day(s)</span><strong><?php echo e(format_money($extension['additional_amount'])); ?></strong></div>
<form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-3"><label class="form-label required" for="payment_method">Payment Method</label><select class="form-select" id="payment_method" name="payment_method"><?php foreach ($methods as $availableMethod) : ?><option value="<?php echo e($availableMethod); ?>" <?php echo $method === $availableMethod ? 'selected' : ''; ?>><?php echo e($availableMethod); ?></option><?php endforeach; ?></select></div><div class="mb-4"><label class="form-label required">Demo Result</label><div class="d-flex gap-3"><div class="form-check"><input class="form-check-input" type="radio" name="demo_outcome" id="success" value="success" <?php echo $outcome === 'success' ? 'checked' : ''; ?>><label class="form-check-label" for="success">Successful payment</label></div><div class="form-check"><input class="form-check-input" type="radio" name="demo_outcome" id="failed" value="failed" <?php echo $outcome === 'failed' ? 'checked' : ''; ?>><label class="form-check-label" for="failed">Failed payment</label></div></div></div><button class="btn btn-brand" type="submit">Pay Extension Amount</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form>
</div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
