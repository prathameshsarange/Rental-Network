<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare("SELECT b.booking_id, b.item_id, b.renter_id, b.owner_id, b.start_date, b.end_date, b.booking_status, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.renter_id = ? LIMIT 1");
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();

if (!$booking || !in_array($booking['booking_status'], ['Confirmed', 'Active'], true)) {
    set_flash('danger', 'This booking is not ready for a return request.');
    redirect('booking/my_bookings.php');
}

$page_title = 'Request Return';
$conditions = ['Good', 'Fair', 'Damaged', 'Lost'];
$returnDate = $_POST['return_date'] ?? date('Y-m-d');
$returnCondition = $_POST['return_condition'] ?? 'Good';
$damageDescription = trim($_POST['damage_description'] ?? '');
$errors = [];
$lateFeePerDay = max(0, (float) setting_value($pdo, 'late_fee_per_day', '100'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dateObject = DateTime::createFromFormat('Y-m-d', $returnDate);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $returnDate) {
        $errors[] = 'Select a valid return date.';
    } elseif ($returnDate < $booking['start_date']) {
        $errors[] = 'The return date cannot be before the rental start date.';
    } elseif ($returnDate > date('Y-m-d')) {
        $errors[] = 'The return date cannot be in the future.';
    }
    if (!in_array($returnCondition, $conditions, true)) {
        $errors[] = 'Select a valid item condition.';
    }
    if (in_array($returnCondition, ['Damaged', 'Lost'], true) && $damageDescription === '') {
        $errors[] = 'Please describe the damage or loss.';
    }

    if (empty($errors)) {
        $dateDifference = (new DateTime($booking['end_date']))->diff(new DateTime($returnDate));
        $lateDays = $dateDifference->invert === 1 ? 0 : $dateDifference->days;
        $lateFee = $lateDays * $lateFeePerDay;
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT booking_status FROM bookings WHERE booking_id = ? AND renter_id = ? FOR UPDATE");
            $lock->execute([$bookingId, current_user_id()]);
            $currentBooking = $lock->fetch();
            if (!$currentBooking || !in_array($currentBooking['booking_status'], ['Confirmed', 'Active'], true)) {
                throw new RuntimeException('This booking is no longer ready for a return request.');
            }
            $returnStatement = $pdo->prepare("INSERT INTO `returns` (booking_id, item_id, renter_id, return_date, return_condition, damage_description, late_days, late_fee, return_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Return Requested')");
            $returnStatement->execute([$bookingId, $booking['item_id'], current_user_id(), $returnDate, $returnCondition, $damageDescription ?: null, $lateDays, $lateFee]);
            $returnId = (int) $pdo->lastInsertId();
            $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Return Requested', status_note = 'Renter requested return confirmation.' WHERE booking_id = ?");
            $update->execute([$bookingId]);
            create_notification($pdo, (int) $booking['owner_id'], 'Return request received for ' . $booking['item_name'] . '.', 'Return Request', $returnId);
            create_notification($pdo, current_user_id(), 'Your return request for ' . $booking['item_name'] . ' has been sent.', 'Return Request', $returnId);
            $pdo->commit();
            set_flash('success', 'Return request sent to the owner.');
            redirect('returns/my_returns.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The return request could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="h3 mb-1">Request Return</h1><p class="text-muted mb-0">Provide the return condition for <?php echo e($booking['item_name']); ?>.</p></div><div class="card shadow-sm"><div class="card-body p-4">
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label required" for="return_date">Return Date</label><input class="form-control" type="date" id="return_date" name="return_date" required value="<?php echo e($returnDate); ?>"></div><div class="col-md-6"><label class="form-label required" for="return_condition">Item Condition</label><select class="form-select" id="return_condition" name="return_condition"><?php foreach ($conditions as $condition) : ?><option value="<?php echo e($condition); ?>" <?php echo $returnCondition === $condition ? 'selected' : ''; ?>><?php echo e($condition); ?></option><?php endforeach; ?></select></div></div><div class="mb-4 mt-3"><label class="form-label" for="damage_description">Damage or Return Notes</label><textarea class="form-control" id="damage_description" name="damage_description" rows="4" placeholder="Describe any damage, missing parts, or other return details."><?php echo e($damageDescription); ?></textarea></div><div class="return-fee mb-4"><span>Late fee rate</span><strong><?php echo e(format_money($lateFeePerDay)); ?> per day</strong></div><button class="btn btn-brand" type="submit">Send Return Request</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form>
</div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
