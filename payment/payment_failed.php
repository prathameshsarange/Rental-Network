<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$paymentId = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare("SELECT p.*, b.booking_id, i.item_name FROM payments p INNER JOIN bookings b ON b.booking_id = p.booking_id INNER JOIN items i ON i.item_id = b.item_id WHERE p.payment_id = ? AND p.user_id = ? AND p.payment_status = 'Failed' LIMIT 1");
$statement->execute([$paymentId, current_user_id()]);
$payment = $statement->fetch();
if (!$payment) { set_flash('danger', 'Payment record not found.'); redirect('payment/payment_history.php'); }
$page_title = 'Payment Failed';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card shadow-sm"><div class="card-body text-center p-5"><h1 class="h3 text-danger mb-3">Payment Failed</h1><p>Your demo payment for <strong><?php echo e($payment['item_name']); ?></strong> was not completed.</p><p class="text-muted small">Reference: <?php echo e($payment['transaction_reference']); ?></p><a class="btn btn-brand" href="<?php echo e(base_url('payment/payment.php?booking_id=' . $payment['booking_id'])); ?>">Try Again</a> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $payment['booking_id'])); ?>">View Booking</a></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
