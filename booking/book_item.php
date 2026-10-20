<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
if ($itemId < 1) {
    set_flash('danger', 'Invalid item selected.');
    redirect('items/browse_items.php');
}

$itemStatement = $pdo->prepare("SELECT i.item_id, i.owner_id, i.item_name, i.rental_price, i.rental_unit, i.hourly_price, i.security_deposit, i.status, i.is_approved, c.category_name FROM items i INNER JOIN categories c ON c.category_id = i.category_id WHERE i.item_id = ? AND c.status = 'Active' LIMIT 1");
$itemStatement->execute([$itemId]);
$item = $itemStatement->fetch();

if (!$item || !(bool) $item['is_approved'] || $item['status'] !== 'Available') {
    set_flash('danger', 'This item is not available for booking.');
    redirect('items/browse_items.php');
}

if ((int) $item['owner_id'] === current_user_id()) {
    set_flash('warning', 'You cannot book your own item.');
    redirect('items/item_details.php?id=' . $itemId);
}

$page_title = 'Book ' . $item['item_name'];
$errors = [];
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$rentalUnit = $_POST['rental_unit'] ?? ($item['rental_unit'] === 'Hourly' ? 'Hourly' : 'Daily');
$allowedUnits = $item['rental_unit'] === 'Both' ? ['Daily', 'Hourly'] : [$item['rental_unit']];

$unavailableStatement = $pdo->prepare("SELECT start_date, end_date FROM bookings WHERE item_id = ? AND booking_status IN ('Pending', 'Accepted', 'Payment Pending', 'Confirmed', 'Active', 'Return Requested', 'Returned') AND end_date >= CURDATE() ORDER BY start_date ASC");
$unavailableStatement->execute([$itemId]);
$unavailableDates = $unavailableStatement->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    $today = new DateTime('today');

    if (!in_array($rentalUnit, $allowedUnits, true)) {
        $errors[] = 'This rental mode is not available for this item.';
    } elseif (!$start || $start->format('Y-m-d') !== $startDate || !$end || $end->format('Y-m-d') !== $endDate) {
        $errors[] = 'Select valid rental dates.';
    } elseif ($start < $today) {
        $errors[] = 'The start date cannot be in the past.';
    } elseif ($end < $start) {
        $errors[] = 'The end date must be on or after the start date.';
    }

    $startAt = null;
    $endAt = null;
    if ($rentalUnit === 'Hourly') {
        $startAt = DateTime::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime);
        $endAt = DateTime::createFromFormat('Y-m-d H:i', $endDate . ' ' . $endTime);
        if ($startDate !== $endDate) {
            $errors[] = 'Hourly rentals must start and end on the same day.';
        } elseif (!$startAt || !$endAt || $endAt <= $startAt) {
            $errors[] = 'Choose a valid end time after the start time.';
        }
    }

    if (empty($errors)) {
        $totalDays = $start->diff($end)->days + 1;
        $totalHours = null;
        if ($rentalUnit === 'Hourly') {
            $totalHours = (int) ceil(($endAt->getTimestamp() - $startAt->getTimestamp()) / 3600);
            $rentalAmount = $totalHours * (float) $item['hourly_price'];
            $totalDays = 1;
        } else {
            $rentalAmount = $totalDays * (float) $item['rental_price'];
            $startTime = null;
            $endTime = null;
        }
        $deposit = (float) $item['security_deposit'];

        try {
            $pdo->beginTransaction();
            $lockedItem = $pdo->prepare("SELECT owner_id, status, is_approved FROM items WHERE item_id = ? FOR UPDATE");
            $lockedItem->execute([$itemId]);
            $currentItem = $lockedItem->fetch();

            if (!$currentItem || $currentItem['status'] !== 'Available' || !(bool) $currentItem['is_approved']) {
                throw new RuntimeException('This item is no longer available for booking.');
            }
            if ($rentalUnit === 'Hourly' ? has_hourly_booking_conflict($pdo, $itemId, $startDate, $startTime, $endTime) : has_booking_conflict($pdo, $itemId, $startDate, $endDate)) {
                throw new RuntimeException('This item already has a booking request for the selected rental period. Please choose another slot.');
            }

            $bookingStatement = $pdo->prepare("INSERT INTO bookings (item_id, renter_id, owner_id, start_date, end_date, rental_unit, start_time, end_time, total_days, total_hours, rental_amount, security_deposit, total_amount, booking_status, status_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Waiting for owner approval.')");
            $bookingStatement->execute([$itemId, current_user_id(), $currentItem['owner_id'], $startDate, $endDate, $rentalUnit, $startTime ?: null, $endTime ?: null, $totalDays, $totalHours, $rentalAmount, $deposit, $rentalAmount + $deposit]);
            $bookingId = (int) $pdo->lastInsertId();
            create_notification($pdo, (int) $currentItem['owner_id'], 'New rental request received for ' . $item['item_name'] . '.', 'New Rental Request', $bookingId);
            $pdo->commit();

            set_flash('success', 'Your rental request has been sent to the owner.');
            redirect('booking/booking_details.php?id=' . $bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The booking request could not be created. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4">
            <p class="text-brand fw-semibold mb-1"><?php echo e($item['category_name']); ?></p>
            <h1 class="h3 mb-1">Book <?php echo e($item['item_name']); ?></h1>
            <p class="text-muted mb-0">Choose the dates you need this item.</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($errors)) : ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" data-rental-calculator data-availability-url="<?php echo e(base_url('booking/check_availability.php')); ?>" data-item-id="<?php echo e($item['item_id']); ?>" data-daily-price="<?php echo e($item['rental_price']); ?>" data-hourly-price="<?php echo e($item['hourly_price'] ?? '0'); ?>" data-deposit="<?php echo e($item['security_deposit']); ?>">
                    <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                    <?php if ($item['rental_unit'] === 'Both') : ?>
                        <div class="mb-4"><label for="rental_unit" class="form-label required">Rental Type</label><select class="form-select" id="rental_unit" name="rental_unit"><option value="Daily" <?php echo $rentalUnit === 'Daily' ? 'selected' : ''; ?>>Daily rental</option><option value="Hourly" <?php echo $rentalUnit === 'Hourly' ? 'selected' : ''; ?>>Hourly rental</option></select></div>
                    <?php else : ?>
                        <input type="hidden" name="rental_unit" value="<?php echo e($item['rental_unit']); ?>">
                        <div class="alert alert-info">This item is available for <strong><?php echo e(strtolower($item['rental_unit'])); ?></strong> rentals.</div>
                    <?php endif; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label required">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" min="<?php echo e(date('Y-m-d')); ?>" required value="<?php echo e($startDate); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label required">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" min="<?php echo e(date('Y-m-d')); ?>" required value="<?php echo e($endDate); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-4" data-hourly-times <?php echo $rentalUnit === 'Hourly' ? '' : 'hidden'; ?>>
                        <div class="col-md-6"><label for="start_time" class="form-label">Start Time</label><input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo e($startTime); ?>"></div>
                        <div class="col-md-6"><label for="end_time" class="form-label">End Time</label><input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo e($endTime); ?>"></div>
                    </div>

                    <div class="booking-summary mb-4">
                        <div><span>Daily rental price</span><strong><?php echo e(format_money($item['rental_price'])); ?></strong></div>
                        <div><span>Rental duration</span><strong data-rental-days>-</strong></div>
                        <div><span>Rental amount</span><strong data-rental-amount>-</strong></div>
                        <div><span>Security deposit</span><strong><?php echo e(format_money($item['security_deposit'])); ?></strong></div>
                        <div class="booking-summary-total"><span>Total payable</span><strong data-rental-total>-</strong></div>
                    </div>

                    <div class="availability-feedback mb-4" data-availability-feedback aria-live="polite">Select both dates to check availability.</div>

                    <?php if (!empty($unavailableDates)) : ?>
                        <div class="unavailable-dates mb-4"><strong class="d-block mb-2">Already requested or reserved</strong><?php foreach ($unavailableDates as $period) : ?><span><?php echo e(date('d M Y', strtotime($period['start_date']))); ?> to <?php echo e(date('d M Y', strtotime($period['end_date']))); ?></span><?php endforeach; ?></div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-brand" data-booking-submit>Send Rental Request</button>
                        <a href="<?php echo e(base_url('items/item_details.php?id=' . $item['item_id'])); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
