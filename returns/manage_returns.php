<?php
require_once __DIR__ . '/../includes/db.php';

require_login();
$page_title = 'Manage Returns';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnId = (int) ($_POST['return_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $ownerNote = trim($_POST['owner_note'] ?? '');

    $returnStatement = $pdo->prepare("SELECT r.*, b.owner_id, b.booking_id, i.item_name FROM `returns` r INNER JOIN bookings b ON b.booking_id = r.booking_id INNER JOIN items i ON i.item_id = r.item_id WHERE r.return_id = ? AND b.owner_id = ? LIMIT 1");
    $returnStatement->execute([$returnId, current_user_id()]);
    $return = $returnStatement->fetch();

    if (!$return || $return['return_status'] !== 'Return Requested') {
        $errors[] = 'This return request can no longer be updated.';
    } elseif (!in_array($action, ['approve', 'dispute'], true)) {
        $errors[] = 'Select a valid return action.';
    } elseif ($action === 'dispute' && $ownerNote === '') {
        $errors[] = 'Add a note explaining the damage or dispute.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $note = trim(($return['damage_description'] ?: '') . ($ownerNote !== '' ? ($return['damage_description'] ? "\n\nOwner note: " : 'Owner note: ') . $ownerNote : ''));
            if ($action === 'approve') {
                $updateReturn = $pdo->prepare("UPDATE `returns` SET return_status = 'Approved', damage_description = ? WHERE return_id = ? AND return_status = 'Return Requested'");
                $updateReturn->execute([$note ?: null, $returnId]);
                $updateBooking = $pdo->prepare("UPDATE bookings SET booking_status = 'Completed', status_note = 'Return approved by owner. Rental completed.' WHERE booking_id = ? AND booking_status = 'Return Requested'");
                $updateBooking->execute([$return['booking_id']]);
                $itemUpdate = $pdo->prepare("UPDATE items SET status = 'Available' WHERE item_id = ? AND status = 'Rented'");
                $itemUpdate->execute([$return['item_id']]);
                create_notification($pdo, (int) $return['renter_id'], 'Your return for ' . $return['item_name'] . ' has been approved. Rental completed.', 'Return Accepted', $returnId);
                set_flash('success', 'Return approved and booking completed.');
            } else {
                $updateReturn = $pdo->prepare("UPDATE `returns` SET return_status = 'Disputed', damage_description = ? WHERE return_id = ? AND return_status = 'Return Requested'");
                $updateReturn->execute([$note, $returnId]);
                $updateBooking = $pdo->prepare("UPDATE bookings SET booking_status = 'Returned', status_note = 'Return marked for damage review by owner.' WHERE booking_id = ? AND booking_status = 'Return Requested'");
                $updateBooking->execute([$return['booking_id']]);
                create_notification($pdo, (int) $return['renter_id'], 'Your return for ' . $return['item_name'] . ' needs a damage review. Check the return notes.', 'Return Disputed', $returnId);
                set_flash('warning', 'Return marked for damage review.');
            }
            if ($updateReturn->rowCount() !== 1 || $updateBooking->rowCount() !== 1) {
                throw new RuntimeException('This return request has already been updated.');
            }
            $pdo->commit();
            redirect('returns/manage_returns.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The return request could not be updated. Please try again.';
        }
    }
}

$statement = $pdo->prepare("SELECT r.*, b.booking_id, i.item_name, u.name AS renter_name, u.phone AS renter_phone FROM `returns` r INNER JOIN bookings b ON b.booking_id = r.booking_id INNER JOIN items i ON i.item_id = r.item_id INNER JOIN users u ON u.user_id = r.renter_id WHERE b.owner_id = ? ORDER BY CASE WHEN r.return_status = 'Return Requested' THEN 0 ELSE 1 END, r.created_at DESC");
$statement->execute([current_user_id()]);
$returns = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Manage Returns</h1><p class="text-muted mb-0">Review returned items and resolve condition or damage reports.</p></div>
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!$returns) : ?><div class="card shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No returns to manage</h2><p class="text-muted mb-0">Return requests for your items will appear here.</p></div></div><?php else : ?><div class="vstack gap-3"><?php foreach ($returns as $return) : ?><div class="card shadow-sm"><div class="card-body"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><div class="d-flex align-items-center gap-2 mb-2"><h2 class="h5 mb-0"><?php echo e($return['item_name']); ?></h2><span class="badge text-bg-<?php echo e(status_badge_class($return['return_status'])); ?>"><?php echo e($return['return_status']); ?></span></div><p class="mb-1">Renter: <?php echo e($return['renter_name']); ?> | <?php echo e($return['renter_phone']); ?></p><p class="small text-muted mb-1">Return date: <?php echo e(date('d M Y', strtotime($return['return_date']))); ?> | Condition: <?php echo e($return['return_condition']); ?> | Late fee: <?php echo e(format_money($return['late_fee'])); ?></p><?php if ($return['damage_description']) : ?><p class="small mb-0"><strong>Notes:</strong> <?php echo nl2br(e($return['damage_description'])); ?></p><?php endif; ?></div><?php if ($return['return_status'] === 'Return Requested') : ?><div class="return-actions"><form method="post"><input type="hidden" name="return_id" value="<?php echo e($return['return_id']); ?>"><div class="mb-2"><label class="visually-hidden" for="owner_note_<?php echo e($return['return_id']); ?>">Owner note</label><textarea class="form-control form-control-sm" id="owner_note_<?php echo e($return['return_id']); ?>" name="owner_note" rows="2" placeholder="Optional approval note or required dispute note"></textarea></div><div class="d-flex gap-2"><button class="btn btn-sm btn-brand" name="action" value="approve" type="submit">Approve</button><button class="btn btn-sm btn-outline-danger" name="action" value="dispute" type="submit">Report Damage</button></div></form></div><?php endif; ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
