<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$paymentId = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare("SELECT p.*, b.booking_id, i.item_name FROM payments p INNER JOIN bookings b ON b.booking_id = p.booking_id INNER JOIN items i ON i.item_id = b.item_id WHERE p.payment_id = ? AND p.user_id = ? AND p.payment_status = 'Paid' LIMIT 1");
$statement->execute([$paymentId, current_user_id()]);
$payment = $statement->fetch();
if (!$payment) { set_flash('danger', 'Payment record not found.'); redirect('payment/payment_history.php'); }
$page_title = 'Payment Successful';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card shadow-sm"><div class="card-body text-center p-5"><h1 class="h3 text-success mb-3">Payment Successful</h1><p class="mb-1">Your booking for <strong><?php echo e($payment['item_name']); ?></strong> is confirmed.</p><p class="text-muted small">Reference: <?php echo e($payment['transaction_reference']); ?></p><p class="h4 mb-4"><?php echo e(format_money($payment['amount'])); ?></p><a class="btn btn-brand" href="<?php echo e(base_url('booking/booking_details.php?id=' . $payment['booking_id'])); ?>">View Booking</a> <a class="btn btn-outline-brand" href="<?php echo e(base_url('payment/payment_history.php')); ?>">Payment History</a></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
