<?php
require_once __DIR__ . '/db.php';

$navUser = null;
$unreadCount = 0;

try {
    // Page visits provide a small fallback when the local scheduler is not running.
    send_rental_due_reminders($pdo);
} catch (Throwable $exception) {
    error_log('Rental reminder error: ' . $exception->getMessage());
}

if (is_logged_in()) {
    try {
        $navUser = current_user($pdo);
        if ($navUser) {
            $unreadCount = unread_notification_count($pdo, (int) $navUser['user_id']);
        }
    } catch (Throwable $exception) {
        $navUser = null;
        $unreadCount = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-light site-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand brand-lockup" href="<?php echo e(base_url('index.php')); ?>">
            <span class="brand-mark" aria-hidden="true">R</span>
            <span>Rental Network</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo e(base_url('index.php')); ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo e(base_url('items/browse_items.php')); ?>">Browse Items</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo e(base_url('categories/categories.php')); ?>">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo e(base_url('items/locations.php')); ?>">Locations</a>
                </li>
                <?php if (is_logged_in()) : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(base_url('dashboard.php')); ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(base_url('items/my_items.php')); ?>">My Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(base_url('booking/my_bookings.php')); ?>">My Bookings</a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if (is_logged_in() && $navUser) : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(base_url('notifications/notifications.php')); ?>">
                            Notifications
                            <?php if ($unreadCount > 0) : ?>
                                <span class="badge rounded-pill text-bg-danger"><?php echo e($unreadCount); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo e($navUser['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo e(base_url('profile.php')); ?>">Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(base_url('payment/payment_history.php')); ?>">Payment History</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(base_url('returns/my_returns.php')); ?>">My Returns</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(base_url('returns/manage_returns.php')); ?>">Manage Returns</a></li>
                            <?php if (($navUser['role'] ?? '') === 'User') : ?>
                                <li><a class="dropdown-item" href="<?php echo e(base_url('request_admin.php')); ?>">Request Admin Access</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo e(base_url('booking/received_bookings.php')); ?>">Rental Requests</a></li>
                            <li><a class="dropdown-item" href="<?php echo e(base_url('change_password.php')); ?>">Change Password</a></li>
                            <?php if (($navUser['role'] ?? '') === 'Admin') : ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo e(base_url('admin/categories.php')); ?>">Manage Categories</a></li>
                                <li><a class="dropdown-item" href="<?php echo e(base_url('admin/dashboard.php')); ?>">Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo e(base_url('logout.php')); ?>">Logout</a></li>
                        </ul>
                    </li>
                <?php else : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(base_url('login.php')); ?>">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-brand btn-sm" href="<?php echo e(base_url('register.php')); ?>">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
