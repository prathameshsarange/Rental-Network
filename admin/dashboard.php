<?php
require_once __DIR__ . '/../includes/db.php'; require_admin(); $page_title = 'Admin Dashboard';
$stats = ['Total Users' => 'SELECT COUNT(*) FROM users', 'Total Items' => 'SELECT COUNT(*) FROM items', 'Total Bookings' => 'SELECT COUNT(*) FROM bookings', 'Active Rentals' => "SELECT COUNT(*) FROM bookings WHERE booking_status IN ('Confirmed', 'Active')", 'Completed Rentals' => "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Completed'", 'Pending Requests' => "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Pending'", 'Total Payments' => 'SELECT COUNT(*) FROM payments', 'Total Categories' => 'SELECT COUNT(*) FROM categories', 'Total Reviews' => 'SELECT COUNT(*) FROM reviews'];
require_once __DIR__ . '/../includes/header.php'; require __DIR__ . '/_nav.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Admin Dashboard</h1><p class="text-muted mb-0">A quick view of Rental Network activity.</p></div><div class="row g-3"><?php foreach ($stats as $label => $query) : ?><div class="col-sm-6 col-xl-4"><div class="card dashboard-stat shadow-sm h-100"><div class="card-body"><p class="text-muted mb-1"><?php echo e($label); ?></p><p class="display-6 mb-0"><?php echo e(dashboard_count($pdo, $query)); ?></p></div></div></div><?php endforeach; ?></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
