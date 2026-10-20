<?php
require_once __DIR__ . '/../includes/db.php';
require_login();
$page_title = 'My Returns';
$statement = $pdo->prepare("SELECT r.*, b.booking_id, i.item_name FROM `returns` r INNER JOIN bookings b ON b.booking_id = r.booking_id INNER JOIN items i ON i.item_id = r.item_id WHERE r.renter_id = ? ORDER BY r.created_at DESC");
$statement->execute([current_user_id()]);
$returns = $statement->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-4"><div><h1 class="h3 mb-1">My Returns</h1><p class="text-muted mb-0">Track the return requests you have submitted.</p></div><a class="btn btn-outline-brand" href="<?php echo e(base_url('booking/my_bookings.php')); ?>">My Bookings</a></div>
<?php if (!$returns) : ?><div class="card shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No return requests yet</h2><p class="text-muted mb-0">Your submitted item returns will appear here.</p></div></div><?php else : ?><div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Item</th><th>Returned</th><th>Condition</th><th>Late fee</th><th>Status</th><th class="pe-3">Details</th></tr></thead><tbody><?php foreach ($returns as $return) : ?><tr><td class="ps-3"><a href="<?php echo e(base_url('booking/booking_details.php?id=' . $return['booking_id'])); ?>"><?php echo e($return['item_name']); ?></a></td><td><?php echo e(date('d M Y', strtotime($return['return_date']))); ?></td><td><?php echo e($return['return_condition']); ?></td><td><?php echo e(format_money($return['late_fee'])); ?></td><td><span class="badge text-bg-<?php echo e(status_badge_class($return['return_status'])); ?>"><?php echo e($return['return_status']); ?></span></td><td class="pe-3" title="<?php echo e($return['damage_description'] ?: 'No additional notes'); ?>">View booking</td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
