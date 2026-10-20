<?php
require_once __DIR__ . '/includes/db.php';

require_login();

$page_title = 'Dashboard';
$user = current_user($pdo);

if (!$user) {
    set_flash('danger', 'Your account could not be found. Please login again.');
    redirect('logout.php');
}

$userId = (int) $user['user_id'];

$stats = [
    'My Items' => dashboard_count($pdo, 'SELECT COUNT(*) FROM items WHERE owner_id = ?', [$userId]),
    'Active Rentals' => dashboard_count($pdo, "SELECT COUNT(*) FROM bookings WHERE (renter_id = ? OR owner_id = ?) AND booking_status IN ('Confirmed', 'Active')", [$userId, $userId]),
    'My Bookings' => dashboard_count($pdo, 'SELECT COUNT(*) FROM bookings WHERE renter_id = ?', [$userId]),
    'Rental Requests' => dashboard_count($pdo, 'SELECT COUNT(*) FROM bookings WHERE owner_id = ?', [$userId]),
    'Payment Pending' => dashboard_count($pdo, "SELECT COUNT(*) FROM bookings WHERE owner_id = ? AND booking_status = 'Payment Pending'", [$userId]),
    'Completed Rentals' => dashboard_count($pdo, "SELECT COUNT(*) FROM bookings WHERE (renter_id = ? OR owner_id = ?) AND booking_status = 'Completed'", [$userId, $userId]),
    'Notifications' => unread_notification_count($pdo, $userId),
];

$recentStatement = $pdo->prepare("
    SELECT b.booking_id, b.start_date, b.end_date, b.booking_status, i.item_name
    FROM bookings b
    INNER JOIN items i ON i.item_id = b.item_id
    WHERE b.renter_id = ? OR b.owner_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$recentStatement->execute([$userId, $userId]);
$recentBookings = $recentStatement->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-5 gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">Welcome back, <?php echo e(explode(' ', $user['name'])[0]); ?>! 👋</h1>
            <p class="text-muted mb-0">Here's what's happening with your rentals today.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="btn btn-outline-brand">Find Items</a>
            <a href="<?php echo e(base_url('items/add_item.php')); ?>" class="btn btn-brand"><span aria-hidden="true">+</span> List New Item</a>
            <?php if ($user['role'] === 'Admin') : ?>
                <a href="<?php echo e(base_url('admin/dashboard.php')); ?>" class="btn btn-dark bg-dark">Admin Panel</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="row g-4 mb-5">
        <!-- Revenue / Core Metric -->
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <p class="text-muted fw-semibold mb-0">Active Rentals</p>
                        <div class="bg-brand-subtle text-brand rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            🔄
                        </div>
                    </div>
                    <p class="display-5 fw-bold mb-0 text-dark"><?php echo e($stats['Active Rentals']); ?></p>
                </div>
                <div class="position-absolute bottom-0 start-0 w-100" style="height: 4px; background: var(--brand-emerald);"></div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <p class="text-muted fw-semibold mb-0">My Items</p>
                        <div class="bg-surface-alt rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            📦
                        </div>
                    </div>
                    <p class="display-5 fw-bold mb-0 text-dark"><?php echo e($stats['My Items']); ?></p>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <p class="text-muted fw-semibold mb-0">Rental Requests</p>
                        <div class="bg-warning-subtle text-warning-emphasis rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            🔔
                        </div>
                    </div>
                    <p class="display-5 fw-bold mb-0 text-dark"><?php echo e($stats['Rental Requests']); ?></p>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <p class="text-muted fw-semibold mb-0">Completed</p>
                        <div class="bg-success-subtle text-success-emphasis rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            ✅
                        </div>
                    </div>
                    <p class="display-5 fw-bold mb-0 text-dark"><?php echo e($stats['Completed Rentals']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content Area: Recent Bookings -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom border-subtle d-flex justify-content-between align-items-center p-4">
                    <h2 class="h5 fw-bold mb-0">Recent Activity</h2>
                    <a href="<?php echo e(base_url('booking/my_bookings.php')); ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentBookings)) : ?>
                        <div class="p-5 text-center">
                            <div class="display-1 text-muted mb-3 opacity-25">📅</div>
                            <h3 class="h5 fw-semibold">No recent bookings</h3>
                            <p class="text-muted mb-4">You don't have any recent rental activity.</p>
                            <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="btn btn-brand">Explore Items</a>
                        </div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 custom-table">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold">Item</th>
                                        <th class="py-3 fw-semibold">Dates</th>
                                        <th class="py-3 fw-semibold">Status</th>
                                        <th class="pe-4 py-3 fw-semibold text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="border-top-0">
                                    <?php foreach ($recentBookings as $booking) : ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-semibold text-dark"><?php echo e($booking['item_name']); ?></div>
                                                <div class="small text-muted">ID: #<?php echo e($booking['booking_id']); ?></div>
                                            </td>
                                            <td class="py-3">
                                                <div class="text-dark"><?php echo e(date('M d', strtotime($booking['start_date']))); ?> - <?php echo e(date('M d, Y', strtotime($booking['end_date']))); ?></div>
                                            </td>
                                            <td class="py-3">
                                                <span class="badge badge-<?php echo e(strtolower(str_replace(' ', '-', $booking['booking_status']))); ?> px-2 py-1 rounded-pill fw-medium">
                                                    <?php echo e($booking['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 py-3 text-end">
                                                <a href="<?php echo e(base_url('booking/booking_details.php?id=' . $booking['booking_id'])); ?>" class="btn btn-sm btn-light text-brand fw-medium">Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Area: Quick Links -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-bottom border-subtle p-4">
                    <h2 class="h5 fw-bold mb-0">Quick Actions</h2>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-3">
                        <a href="<?php echo e(base_url('items/my_items.php')); ?>" class="text-decoration-none p-3 border border-subtle rounded-3 d-flex align-items-center justify-content-between hover-bg-surface-alt transition-all">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-surface-alt rounded p-2 text-dark fs-5">📦</div>
                                <span class="fw-semibold text-dark">Manage My Items</span>
                            </div>
                            <span class="text-muted">→</span>
                        </a>
                        
                        <a href="<?php echo e(base_url('booking/my_bookings.php')); ?>" class="text-decoration-none p-3 border border-subtle rounded-3 d-flex align-items-center justify-content-between hover-bg-surface-alt transition-all">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-surface-alt rounded p-2 text-dark fs-5">📅</div>
                                <span class="fw-semibold text-dark">My Bookings</span>
                            </div>
                            <span class="text-muted">→</span>
                        </a>

                        <a href="<?php echo e(base_url('notifications/notifications.php')); ?>" class="text-decoration-none p-3 border border-subtle rounded-3 d-flex align-items-center justify-content-between hover-bg-surface-alt transition-all">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-surface-alt rounded p-2 text-dark fs-5 position-relative">
                                    🔔
                                    <?php if($stats['Notifications'] > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                        <?php echo e($stats['Notifications']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="fw-semibold text-dark">Notifications</span>
                            </div>
                            <span class="text-muted">→</span>
                        </a>

                        <a href="<?php echo e(base_url('profile.php')); ?>" class="text-decoration-none p-3 border border-subtle rounded-3 d-flex align-items-center justify-content-between hover-bg-surface-alt transition-all">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-surface-alt rounded p-2 text-dark fs-5">👤</div>
                                <span class="fw-semibold text-dark">Edit Profile</span>
                            </div>
                            <span class="text-muted">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
.hover-bg-surface-alt:hover { background-color: var(--surface-alt); }
.custom-table th { font-size: 0.75rem; letter-spacing: 0.5px; }
.custom-table td { vertical-align: middle; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
