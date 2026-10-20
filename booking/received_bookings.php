<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$page_title = 'Rental Requests';

$statement = $pdo->prepare("SELECT b.booking_id, b.start_date, b.end_date, b.total_days, b.total_amount, b.booking_status, b.created_at, i.item_name, u.name AS renter_name, u.phone AS renter_phone FROM bookings b INNER JOIN items i ON i.item_id = b.item_id INNER JOIN users u ON u.user_id = b.renter_id WHERE b.owner_id = ? ORDER BY CASE WHEN b.booking_status = 'Pending' THEN 0 ELSE 1 END, b.created_at DESC");
$statement->execute([current_user_id()]);
$bookings = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4"><h1 class="h3 mb-1">Rental Requests</h1><p class="text-muted mb-0">Review requests for the items you have listed.</p></div>

<?php if (empty($bookings)) : ?>
    <div class="card shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No rental requests yet</h2><p class="text-muted mb-0">Requests for your listed items will appear here.</p></div></div>
<?php else : ?>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Item and renter</th><th>Rental dates</th><th>Amount</th><th>Status</th><th class="text-end pe-3">Action</th></tr></thead><tbody>
        <?php foreach ($bookings as $booking) : ?>
            <tr><td class="ps-3"><div class="fw-semibold"><?php echo e($booking['item_name']); ?></div><div class="small text-muted"><?php echo e($booking['renter_name']); ?> | <?php echo e($booking['renter_phone']); ?></div></td><td><?php echo e($booking['start_date']); ?><br><span class="small text-muted">to <?php echo e($booking['end_date']); ?></span></td><td><?php echo e(format_money($booking['total_amount'])); ?></td><td><span class="badge text-bg-<?php echo e(status_badge_class($booking['booking_status'])); ?>"><?php echo e($booking['booking_status']); ?></span></td><td class="text-end pe-3"><a class="btn btn-sm btn-outline-brand" href="<?php echo e(base_url('booking/booking_details.php?id=' . $booking['booking_id'])); ?>">Review</a></td></tr>
        <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
