<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$page_title = 'My Bookings';

$statement = $pdo->prepare("SELECT b.booking_id, b.start_date, b.end_date, b.rental_unit, b.start_time, b.end_time, b.total_days, b.total_hours, b.total_amount, b.booking_status, b.created_at, i.item_id, i.item_name, i.image, u.name AS owner_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id INNER JOIN users u ON u.user_id = b.owner_id WHERE b.renter_id = ? ORDER BY b.created_at DESC");
$statement->execute([current_user_id()]);
$bookings = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-5 border-bottom border-subtle pb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">My Bookings</h1>
            <p class="text-muted mb-0">Track the items you have requested to rent.</p>
        </div>
        <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="btn btn-brand shadow-sm">
            <span aria-hidden="true">🔍</span> Browse Items
        </a>
    </div>

    <?php if (empty($bookings)) : ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center p-5">
                <div class="display-1 text-muted opacity-25 mb-3">📅</div>
                <h2 class="h4 fw-bold">No bookings yet</h2>
                <p class="text-muted mb-4">Browse available items to send your first rental request.</p>
                <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="btn btn-brand btn-lg">Explore Items</a>
            </div>
        </div>
    <?php else : ?>
        <div class="row g-4">
            <?php foreach ($bookings as $booking) : ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift transition-all">
                        <div class="card-body d-flex flex-column p-4">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom border-subtle">
                                <div>
                                    <h2 class="h5 fw-bold mb-1 text-dark"><?php echo e($booking['item_name']); ?></h2>
                                    <p class="small text-muted mb-0">ID: #<?php echo e($booking['booking_id']); ?></p>
                                </div>
                                <span class="badge badge-<?php echo e(strtolower(str_replace(' ', '-', $booking['booking_status']))); ?> px-2 py-1 rounded-pill">
                                    <?php echo e($booking['booking_status']); ?>
                                </span>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="bg-surface-alt rounded-circle d-flex align-items-center justify-content-center text-dark" style="width: 32px; height: 32px; font-size: 0.8rem;">👤</div>
                                <div class="small text-muted">Owner: <span class="text-dark fw-medium"><?php echo e($booking['owner_name']); ?></span></div>
                            </div>
                            
                            <div class="bg-surface-alt rounded-3 p-3 mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small">Dates</span>
                                    <span class="text-dark fw-medium small"><?php echo e(date('M d', strtotime($booking['start_date']))); ?> - <?php echo e(date('M d, Y', strtotime($booking['end_date']))); ?><?php if ($booking['rental_unit'] === 'Hourly') : ?><br><?php echo e(date('g:i A', strtotime($booking['start_time']))); ?> - <?php echo e(date('g:i A', strtotime($booking['end_time']))); ?><?php endif; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-muted small">Total (<?php echo $booking['rental_unit'] === 'Hourly' ? e((string) $booking['total_hours']) . ' hours' : e((string) $booking['total_days']) . ' days'; ?>)</span>
                                    <span class="text-brand fw-bold"><?php echo e(format_money($booking['total_amount'])); ?></span>
                                </div>
                            </div>
                            
                            <a class="btn btn-outline-brand w-100 mt-auto shadow-sm" href="<?php echo e(base_url('booking/booking_details.php?id=' . $booking['booking_id'])); ?>">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
.hover-lift:hover { transform: translateY(-4px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
