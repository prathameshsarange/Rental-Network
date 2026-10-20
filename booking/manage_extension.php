<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$extensionStatement = $pdo->prepare("SELECT e.*, b.item_id, b.booking_status, i.item_name, r.name AS renter_name FROM booking_extensions e INNER JOIN bookings b ON b.booking_id = e.booking_id INNER JOIN items i ON i.item_id = b.item_id INNER JOIN users r ON r.user_id = e.renter_id WHERE e.booking_id = ? AND e.owner_id = ? AND e.extension_status = 'Pending' ORDER BY e.created_at DESC LIMIT 1");
$extensionStatement->execute([$bookingId, current_user_id()]);
$extension = $extensionStatement->fetch();

if (!$extension) {
    set_flash('danger', 'No pending extension request was found.');
    redirect('booking/received_bookings.php');
}

$page_title = 'Review Extension';
$ownerNote = trim($_POST['owner_note'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['approve', 'reject'], true)) {
        $errors[] = 'Choose a valid extension action.';
    }
    if ($action === 'reject' && $ownerNote === '') {
        $errors[] = 'Add a reason for rejecting the extension.';
    }
    if (strlen($ownerNote) > 500) {
        $errors[] = 'Owner note must be 500 characters or less.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT e.*, b.item_id FROM booking_extensions e INNER JOIN bookings b ON b.booking_id = e.booking_id WHERE e.extension_id = ? AND e.owner_id = ? FOR UPDATE");
            $lock->execute([$extension['extension_id'], current_user_id()]);
            $locked = $lock->fetch();
            if (!$locked || $locked['extension_status'] !== 'Pending') {
                throw new RuntimeException('This extension request has already been updated.');
            }

            if ($action === 'approve') {
                if (has_booking_conflict($pdo, (int) $locked['item_id'], date('Y-m-d', strtotime($locked['previous_end_date'] . ' +1 day')), $locked['requested_end_date'], (int) $locked['booking_id'])) {
                    throw new RuntimeException('The item is now reserved for part of this extension period.');
                }
                $update = $pdo->prepare("UPDATE booking_extensions SET extension_status = 'Payment Pending', owner_note = ?, reviewed_at = NOW() WHERE extension_id = ? AND extension_status = 'Pending'");
                $update->execute([$ownerNote ?: null, $locked['extension_id']]);
                create_notification($pdo, (int) $locked['renter_id'], 'Your extension request for ' . $extension['item_name'] . ' was approved. Please complete the extra payment.', 'Extension Accepted', (int) $locked['extension_id']);
                $success = 'Extension approved. The renter can now make the extra payment.';
            } else {
                $update = $pdo->prepare("UPDATE booking_extensions SET extension_status = 'Rejected', owner_note = ?, reviewed_at = NOW() WHERE extension_id = ? AND extension_status = 'Pending'");
                $update->execute([$ownerNote, $locked['extension_id']]);
                create_notification($pdo, (int) $locked['renter_id'], 'Your extension request for ' . $extension['item_name'] . ' was rejected. ' . $ownerNote, 'Extension Rejected', (int) $locked['extension_id']);
                $success = 'Extension request rejected.';
            }
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('This extension request has already been updated.');
            }
            $pdo->commit();
            set_flash('success', $success);
            redirect('booking/booking_details.php?id=' . $bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The extension request could not be updated. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="mb-4"><h1 class="h3 mb-1">Review Extension Request</h1><p class="text-muted mb-0">Review <?php echo e($extension['renter_name']); ?>'s request for <?php echo e($extension['item_name']); ?>.</p></div><div class="card shadow-sm"><div class="card-body p-4">
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="location-summary mb-4"><div>Current end date <strong><?php echo e(date('d M Y', strtotime($extension['previous_end_date']))); ?></strong></div><div>Requested end date <strong><?php echo e(date('d M Y', strtotime($extension['requested_end_date']))); ?></strong></div><div>Extra period <strong><?php echo e((string) $extension['additional_days']); ?> day(s)</strong></div><div>Extra payment <strong><?php echo e(format_money($extension['additional_amount'])); ?></strong></div></div>
<?php if ($extension['renter_note']) : ?><div class="mb-4"><strong>Renter note</strong><p class="text-muted mb-0 mt-1"><?php echo nl2br(e($extension['renter_note'])); ?></p></div><?php endif; ?>
<form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-4"><label class="form-label" for="owner_note">Note for renter</label><textarea class="form-control" id="owner_note" name="owner_note" rows="3" maxlength="500" placeholder="Optional when approving, required when rejecting."><?php echo e($ownerNote); ?></textarea></div><button class="btn btn-brand" name="action" value="approve" type="submit">Approve Extension</button> <button class="btn btn-outline-danger" name="action" value="reject" type="submit">Reject Extension</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form>
</div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
