<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId < 1) {
    set_flash('danger', 'Invalid booking selected.');
    redirect('booking/my_bookings.php');
}

$statement = $pdo->prepare("SELECT b.*, i.item_name, i.image, i.location, i.city, i.state, u_renter.name AS renter_name, u_renter.phone AS renter_phone, u_owner.name AS owner_name, u_owner.phone AS owner_phone FROM bookings b INNER JOIN items i ON i.item_id = b.item_id INNER JOIN users u_renter ON u_renter.user_id = b.renter_id INNER JOIN users u_owner ON u_owner.user_id = b.owner_id WHERE b.booking_id = ? LIMIT 1");
$statement->execute([$bookingId]);
$booking = $statement->fetch();

if (!$booking || (int) $booking['renter_id'] !== current_user_id() && (int) $booking['owner_id'] !== current_user_id() && current_user_role() !== 'Admin') {
    set_flash('danger', 'You are not allowed to view this booking.');
    redirect('dashboard.php');
}

$isOwner = (int) $booking['owner_id'] === current_user_id();
$isRenter = (int) $booking['renter_id'] === current_user_id();
$extensionStatement = $pdo->prepare('SELECT * FROM booking_extensions WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1');
$extensionStatement->execute([$bookingId]);
$latestExtension = $extensionStatement->fetch();
$page_title = 'Booking #' . $bookingId;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4 booking-detail-page">
    <!-- Breadcrumb & Header -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(base_url('booking/my_bookings.php')); ?>" class="text-brand text-decoration-none fw-medium">My Bookings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Booking #<?php echo e($booking['booking_id']); ?></li>
        </ol>
    </nav>

    <div class="booking-hero d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <span class="booking-eyebrow">Rental booking</span>
            <h1 class="h3 fw-bold mb-1 text-dark"><?php echo e($booking['item_name']); ?></h1>
            <p class="text-muted mb-0">Reference <span class="text-dark fw-medium">#<?php echo e($booking['booking_id']); ?></span> <span class="mx-2 opacity-50">|</span> <?php echo e($booking['rental_unit']); ?> rental</p>
        </div>
        <span class="booking-status booking-status-<?php echo e(strtolower(str_replace(' ', '-', $booking['booking_status']))); ?>">
            <?php echo e($booking['booking_status']); ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- Main Details -->
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 booking-panel">
                <div class="card-header bg-transparent border-bottom border-subtle p-4">
                    <h2 class="h5 fw-bold mb-0">Rental Details</h2>
                </div>
                <div class="card-body p-4 p-md-5">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="booking-info-tile h-100">
                                <div class="text-muted small fw-semibold text-uppercase tracking-wide mb-1">Rental Period</div>
                                <div class="fs-5 fw-bold text-dark">
                                    <?php echo e(date('M d', strtotime($booking['start_date']))); ?> - <?php echo e(date('M d, Y', strtotime($booking['end_date']))); ?><?php if ($booking['rental_unit'] === 'Hourly') : ?>, <?php echo e(date('g:i A', strtotime($booking['start_time']))); ?> - <?php echo e(date('g:i A', strtotime($booking['end_time']))); ?><?php endif; ?>
                                </div>
                                <div class="text-muted small mt-1"><?php echo $booking['rental_unit'] === 'Hourly' ? e((string) $booking['total_hours']) . ' total hour(s)' : e((string) $booking['total_days']) . ' total days'; ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="booking-total-tile h-100 shadow-sm">
                                <div class="text-white-50 small fw-semibold text-uppercase tracking-wide mb-1">Total Payable</div>
                                <div class="display-6 fw-bold">
                                    <?php echo e(format_money($booking['total_amount'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5 border-top border-subtle pt-4">
                        <h3 class="h6 fw-bold text-uppercase text-muted tracking-wide mb-3">Price Breakdown</h3>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Rental Rate (<?php echo $booking['rental_unit'] === 'Hourly' ? e((string) $booking['total_hours']) . ' hours' : e((string) $booking['total_days']) . ' days'; ?>)</span>
                            <span class="text-dark fw-medium"><?php echo e(format_money($booking['rental_amount'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Security Deposit (Refundable)</span>
                            <span class="text-dark fw-medium"><?php echo e(format_money($booking['security_deposit'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between pt-3 border-top border-subtle">
                            <span class="fw-bold text-dark">Total Amount</span>
                            <span class="fw-bold text-brand fs-5"><?php echo e(format_money($booking['total_amount'])); ?></span>
                        </div>
                    </div>

                    <div class="mt-5 border-top border-subtle pt-4">
                        <h3 class="h6 fw-bold text-uppercase text-muted tracking-wide mb-3">Collection Location</h3>
                        <div class="booking-location d-flex align-items-start gap-3">
                            <div class="text-brand fs-4 mt-n1">📍</div>
                            <div>
                                <p class="mb-0 text-dark fw-medium"><?php echo e($booking['location']); ?></p>
                                <p class="text-muted small mb-0"><?php echo e($booking['city']); ?>, <?php echo e($booking['state']); ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if ($latestExtension) : ?>
                        <div class="mt-5 border-top border-subtle pt-4">
                            <h3 class="h6 fw-bold text-uppercase text-muted tracking-wide mb-3">Latest Extension Request</h3>
                            <div class="location-summary"><div>Status <strong><?php echo e($latestExtension['extension_status']); ?></strong></div><div>Requested end date <strong><?php echo e(date('d M Y', strtotime($latestExtension['requested_end_date']))); ?></strong></div><div>Extra payment <strong><?php echo e(format_money($latestExtension['additional_amount'])); ?></strong></div></div>
                            <?php if ($latestExtension['renter_note']) : ?><p class="small text-muted mt-3 mb-0"><strong>Renter note:</strong> <?php echo e($latestExtension['renter_note']); ?></p><?php endif; ?>
                            <?php if ($latestExtension['owner_note']) : ?><p class="small text-muted mt-2 mb-0"><strong>Owner note:</strong> <?php echo e($latestExtension['owner_note']); ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($booking['status_note'])) : ?>
                <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-warning">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-2 d-flex align-items-center gap-2">
                            <span aria-hidden="true">⚠️</span> Status Update
                        </h2>
                        <p class="mb-0 text-dark"><?php echo e($booking['status_note']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 booking-side-card">
                <div class="card-header bg-transparent border-bottom border-subtle p-4">
                    <h2 class="h5 fw-bold mb-0"><?php echo $isOwner ? 'Renter Contact' : 'Owner Contact'; ?></h2>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="booking-avatar d-flex align-items-center justify-content-center fs-4">
                            <?php echo $isOwner ? strtoupper(substr($booking['renter_name'], 0, 1)) : strtoupper(substr($booking['owner_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold fs-5 text-dark">
                                <?php echo $isOwner ? e($booking['renter_name']) : e($booking['owner_name']); ?>
                            </p>
                            <p class="text-muted mb-0 d-flex align-items-center gap-1">
                                <span aria-hidden="true">📞</span> <?php echo $isOwner ? e($booking['renter_phone']) : e($booking['owner_phone']); ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!$isOwner) : ?>
                        <div class="mt-3 text-center">
                            <a href="<?php echo e(base_url('items/item_details.php?id=' . $booking['item_id'])); ?>" class="btn btn-sm btn-outline-secondary w-100">View Public Item Listing</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 booking-side-card booking-actions-card">
                <div class="card-header bg-transparent border-bottom border-subtle p-4">
                    <h2 class="h5 fw-bold mb-0">Actions</h2>
                </div>
                <div class="card-body p-4">
                    <?php if ($isOwner && $booking['booking_status'] === 'Pending') : ?>
                        <div class="d-flex flex-column gap-3">
                            <form method="post" action="<?php echo e(base_url('booking/accept_booking.php')); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo e($booking['booking_id']); ?>">
                                <button class="btn btn-brand w-100 py-2 fw-medium shadow-sm" type="submit">Accept Request</button>
                            </form>
                            <a class="btn btn-outline-danger w-100 py-2 fw-medium" href="<?php echo e(base_url('booking/reject_booking.php?id=' . $booking['booking_id'])); ?>">Reject Request</a>
                        </div>
                    <?php elseif ($isRenter && $booking['booking_status'] === 'Payment Pending') : ?>
                        <div class="d-flex flex-column gap-3">
                            <div class="alert alert-info border-0 bg-brand-subtle text-brand small mb-0">
                                Request accepted! Please complete the payment to confirm your booking.
                            </div>
                            <a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('payment/payment.php?booking_id=' . $booking['booking_id'])); ?>">Make Payment</a>
                            <a class="btn btn-outline-danger w-100 py-2 fw-medium" href="<?php echo e(base_url('booking/cancel_booking.php?id=' . $booking['booking_id'])); ?>">Cancel Booking</a>
                        </div>
                    <?php elseif ($isRenter && in_array($booking['booking_status'], ['Pending', 'Accepted'], true)) : ?>
                        <div class="d-flex flex-column gap-2">
                            <p class="small text-muted mb-1">Waiting for the owner to review this request.</p>
                            <a class="btn btn-outline-danger w-100 py-2 fw-medium booking-danger-action" href="<?php echo e(base_url('booking/cancel_booking.php?id=' . $booking['booking_id'])); ?>">Cancel Request</a>
                        </div>
                    <?php elseif ($isRenter && in_array($booking['booking_status'], ['Confirmed', 'Active'], true)) : ?>
                        <div class="d-flex flex-column gap-2">
                            <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis small mb-3">
                                Booking is active. Enjoy your rental!
                            </div>
                            <?php if ($booking['rental_unit'] === 'Hourly') : ?>
                                <div class="alert alert-info border-0 bg-brand-subtle text-brand small mb-0">Hourly rentals have a fixed end time. Create a new booking if you need another time slot.</div>
                            <?php elseif ($latestExtension && $latestExtension['extension_status'] === 'Payment Pending') : ?>
                                <a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('payment/extension_payment.php?booking_id=' . $booking['booking_id'])); ?>">Pay Extension Amount</a>
                            <?php elseif (!$latestExtension || !in_array($latestExtension['extension_status'], ['Pending', 'Payment Pending'], true)) : ?>
                                <a class="btn btn-outline-brand w-100 py-2 fw-medium" href="<?php echo e(base_url('booking/request_extension.php?booking_id=' . $booking['booking_id'])); ?>">Request Extension</a>
                            <?php else : ?>
                                <div class="alert alert-info border-0 bg-brand-subtle text-brand small mb-0">Your extension request is waiting for owner approval.</div>
                            <?php endif; ?>
                            <a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('returns/request_return.php?booking_id=' . $booking['booking_id'])); ?>">Request Return</a>
                        </div>
                    <?php elseif ($isOwner && $latestExtension && $latestExtension['extension_status'] === 'Pending') : ?>
                        <div class="d-flex flex-column gap-2"><div class="alert alert-info border-0 bg-brand-subtle text-brand small mb-0">The renter requested extra rental time.</div><a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('booking/manage_extension.php?booking_id=' . $booking['booking_id'])); ?>">Review Extension</a></div>
                    <?php elseif ($isOwner && $booking['booking_status'] === 'Return Requested') : ?>
                        <div class="d-flex flex-column gap-2">
                            <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis small mb-3">
                                Renter has requested to return this item.
                            </div>
                            <a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('returns/manage_returns.php')); ?>">Manage Return</a>
                        </div>
                    <?php elseif ($isRenter && $booking['booking_status'] === 'Completed') : ?>
                        <div class="d-flex flex-column gap-2">
                            <a class="btn btn-brand w-100 py-2 fw-medium shadow-sm" href="<?php echo e(base_url('reviews/add_review.php?booking_id=' . $booking['booking_id'])); ?>">Rate This Rental</a>
                        </div>
                    <?php else : ?>
                        <div class="text-center text-muted p-3 bg-surface-alt rounded-3 border border-subtle">
                            <p class="mb-0 small">No actions available at this stage.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
.tracking-wide { letter-spacing: 0.05em; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
