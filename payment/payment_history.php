<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$page_title = 'Payment History';
$statement = $pdo->prepare("SELECT p.payment_id, p.amount, p.payment_method, p.transaction_reference, p.payment_status, p.payment_date, p.created_at, b.booking_id, i.item_name FROM payments p INNER JOIN bookings b ON b.booking_id = p.booking_id INNER JOIN items i ON i.item_id = b.item_id WHERE p.user_id = ? ORDER BY p.created_at DESC");
$statement->execute([current_user_id()]);
$payments = $statement->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-4"><div><h1 class="h3 mb-1">Payment History</h1><p class="text-muted mb-0">Your demo payment attempts and completed payments.</p></div><a class="btn btn-outline-brand" href="<?php echo e(base_url('booking/my_bookings.php')); ?>">My Bookings</a></div>
<?php if (empty($payments)) : ?><div class="card shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No payments yet</h2><p class="text-muted mb-0">Payment activity will appear here after an owner accepts a rental request.</p></div></div><?php else : ?><div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Item</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th class="pe-3">Date</th></tr></thead><tbody><?php foreach ($payments as $payment) : ?><tr><td class="ps-3"><a href="<?php echo e(base_url('booking/booking_details.php?id=' . $payment['booking_id'])); ?>"><?php echo e($payment['item_name']); ?></a></td><td><?php echo e($payment['payment_method']); ?></td><td class="small"><?php echo e($payment['transaction_reference']); ?></td><td><?php echo e(format_money($payment['amount'])); ?></td><td><span class="badge text-bg-<?php echo e(status_badge_class($payment['payment_status'])); ?>"><?php echo e($payment['payment_status']); ?></span></td><td class="pe-3"><?php echo e(date('d M Y, h:i A', strtotime($payment['payment_date'] ?: $payment['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
