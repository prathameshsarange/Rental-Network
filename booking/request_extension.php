<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare("SELECT b.*, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.renter_id = ? LIMIT 1");
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || !in_array($booking['booking_status'], ['Confirmed', 'Active'], true)) {
    set_flash('danger', 'This booking cannot be extended.');
    redirect('booking/my_bookings.php');
}

if (($booking['rental_unit'] ?? 'Daily') === 'Hourly') {
    set_flash('warning', 'Hourly rentals have a fixed end time. Please create a new booking for another time slot.');
    redirect('booking/booking_details.php?id=' . $bookingId);
}

if ($booking['end_date'] < date('Y-m-d')) {
    set_flash('warning', 'An overdue rental cannot be extended. Please contact the owner and submit a return request.');
    redirect('booking/booking_details.php?id=' . $bookingId);
}

$existingStatement = $pdo->prepare("SELECT extension_id FROM booking_extensions WHERE booking_id = ? AND extension_status IN ('Pending', 'Payment Pending') LIMIT 1");
$existingStatement->execute([$bookingId]);
if ($existingStatement->fetch()) {
    set_flash('warning', 'An extension request is already being processed for this booking.');
    redirect('booking/booking_details.php?id=' . $bookingId);
}

$page_title = 'Request Extension';
$requestedEndDate = $_POST['requested_end_date'] ?? '';
$renterNote = trim($_POST['renter_note'] ?? '');
$errors = [];
$dailyRate = (float) $booking['rental_amount'] / max(1, (int) $booking['total_days']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = DateTime::createFromFormat('Y-m-d', $requestedEndDate);
    if (!$date || $date->format('Y-m-d') !== $requestedEndDate) {
        $errors[] = 'Select a valid new end date.';
    } elseif ($requestedEndDate <= $booking['end_date']) {
        $errors[] = 'The new end date must be after the current end date.';
    } elseif (strlen($renterNote) > 500) {
        $errors[] = 'Extension note must be 500 characters or less.';
    }

    $additionalDays = 0;
    $additionalAmount = 0.0;
    if (!$errors) {
        $additionalDays = (int) ((new DateTimeImmutable($booking['end_date']))->diff(new DateTimeImmutable($requestedEndDate))->days);
        $additionalAmount = round($additionalDays * $dailyRate, 2);
        if (has_booking_conflict($pdo, (int) $booking['item_id'], date('Y-m-d', strtotime($booking['end_date'] . ' +1 day')), $requestedEndDate, $bookingId)) {
            $errors[] = 'This item is already reserved for part of the requested extension period.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT booking_status, end_date FROM bookings WHERE booking_id = ? AND renter_id = ? FOR UPDATE");
            $lock->execute([$bookingId, current_user_id()]);
            $lockedBooking = $lock->fetch();
            if (!$lockedBooking || !in_array($lockedBooking['booking_status'], ['Confirmed', 'Active'], true) || $lockedBooking['end_date'] !== $booking['end_date']) {
                throw new RuntimeException('This booking changed before the extension could be requested.');
            }
            $active = $pdo->prepare("SELECT extension_id FROM booking_extensions WHERE booking_id = ? AND extension_status IN ('Pending', 'Payment Pending') FOR UPDATE");
            $active->execute([$bookingId]);
            if ($active->fetch()) {
                throw new RuntimeException('An extension request is already being processed.');
            }
            $insert = $pdo->prepare('INSERT INTO booking_extensions (booking_id, renter_id, owner_id, previous_end_date, requested_end_date, additional_days, additional_amount, renter_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$bookingId, current_user_id(), $booking['owner_id'], $booking['end_date'], $requestedEndDate, $additionalDays, $additionalAmount, $renterNote ?: null]);
            $extensionId = (int) $pdo->lastInsertId();
            create_notification($pdo, (int) $booking['owner_id'], 'Extension request received for ' . $booking['item_name'] . '.', 'Extension Request', $extensionId);
            $pdo->commit();
            set_flash('success', 'Extension request sent to the owner.');
            redirect('booking/booking_details.php?id=' . $bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The extension request could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="h3 mb-1">Request Rental Extension</h1><p class="text-muted mb-0">Extend your rental for <?php echo e($booking['item_name']); ?>.</p></div><div class="card shadow-sm"><div class="card-body p-4">
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="location-summary mb-4"><div>Current end date <strong><?php echo e(date('d M Y', strtotime($booking['end_date']))); ?></strong></div><div>Rate <strong><?php echo e(format_money($dailyRate)); ?>/day</strong></div></div>
<form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-3"><label class="form-label required" for="requested_end_date">New End Date</label><input class="form-control" type="date" id="requested_end_date" name="requested_end_date" min="<?php echo e(date('Y-m-d', strtotime($booking['end_date'] . ' +1 day'))); ?>" value="<?php echo e($requestedEndDate); ?>" required></div><div class="mb-4"><label class="form-label" for="renter_note">Note for owner <span class="text-muted">(optional)</span></label><textarea class="form-control" id="renter_note" name="renter_note" rows="3" maxlength="500" placeholder="Why do you need more time?"><?php echo e($renterNote); ?></textarea></div><button class="btn btn-brand" type="submit">Send Extension Request</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form>
</div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
