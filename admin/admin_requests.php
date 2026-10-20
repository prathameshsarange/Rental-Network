<?php
require_once __DIR__ . '/../includes/db.php';

require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reviewNote = trim($_POST['review_note'] ?? '');

    $requestStatement = $pdo->prepare("SELECT ar.request_id, ar.user_id, ar.request_status, u.name, u.city, u.state, u.role, u.status FROM admin_requests ar INNER JOIN users u ON u.user_id = ar.user_id WHERE ar.request_id = ? FOR UPDATE");

    try {
        $pdo->beginTransaction();
        $requestStatement->execute([$requestId]);
        $request = $requestStatement->fetch();

        if (!$request || $request['request_status'] !== 'Pending' || $request['role'] !== 'User' || $request['status'] !== 'Active') {
            throw new RuntimeException('This request is no longer eligible for review.');
        }

        if ($action === 'approve') {
            $locationAdminStatement = $pdo->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND status = 'Active' AND city = ? AND state = ? FOR UPDATE");
            $locationAdminStatement->execute([$request['city'], $request['state']]);
            if ($locationAdminStatement->fetch()) {
                throw new RuntimeException('An active admin already exists for ' . $request['city'] . ', ' . $request['state'] . '.');
            }

            $promote = $pdo->prepare("UPDATE users SET role = 'Admin' WHERE user_id = ? AND role = 'User' AND status = 'Active'");
            $promote->execute([$request['user_id']]);
            $updateRequest = $pdo->prepare("UPDATE admin_requests SET request_status = 'Approved', reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE request_id = ? AND request_status = 'Pending'");
            $updateRequest->execute([current_user_id(), $reviewNote ?: 'Approved for this location.', $requestId]);
            if ($promote->rowCount() !== 1 || $updateRequest->rowCount() !== 1) {
                throw new RuntimeException('This request changed before it could be approved.');
            }
            create_notification($pdo, (int) $request['user_id'], 'Your admin access request has been approved. Please log out and log in again.', 'Admin Request Approved', $requestId);
            set_flash('success', 'Admin request approved for ' . $request['city'] . ', ' . $request['state'] . '.');
        } elseif ($action === 'reject') {
            if ($reviewNote === '') {
                throw new RuntimeException('Add a note explaining why the request was not approved.');
            }
            $updateRequest = $pdo->prepare("UPDATE admin_requests SET request_status = 'Rejected', reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE request_id = ? AND request_status = 'Pending'");
            $updateRequest->execute([current_user_id(), $reviewNote, $requestId]);
            if ($updateRequest->rowCount() !== 1) {
                throw new RuntimeException('This request changed before it could be rejected.');
            }
            create_notification($pdo, (int) $request['user_id'], 'Your admin access request was not approved. ' . $reviewNote, 'Admin Request Rejected', $requestId);
            set_flash('success', 'Admin request rejected.');
        } else {
            throw new RuntimeException('Select a valid request action.');
        }
        $pdo->commit();
        redirect('admin/admin_requests.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The request could not be reviewed. Please try again.';
    }
}

$page_title = 'Admin Requests';
$requests = $pdo->query("SELECT ar.*, u.name, u.email, u.city, u.state, reviewer.name AS reviewer_name FROM admin_requests ar INNER JOIN users u ON u.user_id = ar.user_id LEFT JOIN users reviewer ON reviewer.user_id = ar.reviewed_by ORDER BY CASE WHEN ar.request_status = 'Pending' THEN 0 ELSE 1 END, ar.created_at DESC")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Admin Access Requests</h1><p class="text-muted mb-0">Approve only one active admin per city and state.</p></div>
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!$requests) : ?><div class="card shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No admin requests</h2><p class="text-muted mb-0">Submitted user requests will appear here.</p></div></div><?php else : ?><div class="vstack gap-3"><?php foreach ($requests as $request) : ?><div class="card shadow-sm"><div class="card-body"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><div class="d-flex align-items-center gap-2 mb-1"><h2 class="h5 mb-0"><?php echo e($request['name']); ?></h2><span class="badge text-bg-<?php echo e(status_badge_class($request['request_status'])); ?>"><?php echo e($request['request_status']); ?></span></div><p class="small text-muted mb-2"><?php echo e($request['email']); ?> | <?php echo e($request['city']); ?>, <?php echo e($request['state']); ?></p><?php if ($request['request_message']) : ?><p class="mb-2"><?php echo nl2br(e($request['request_message'])); ?></p><?php endif; ?><?php if ($request['review_note']) : ?><p class="small mb-0"><strong>Review note:</strong> <?php echo e($request['review_note']); ?></p><?php endif; ?></div><?php if ($request['request_status'] === 'Pending') : ?><form method="post" class="admin-request-actions"><input type="hidden" name="request_id" value="<?php echo e($request['request_id']); ?>"><textarea class="form-control form-control-sm mb-2" name="review_note" rows="2" maxlength="500" placeholder="Optional approval note; required for rejection"></textarea><div class="d-flex gap-2"><button class="btn btn-sm btn-brand" name="action" value="approve">Approve</button><button class="btn btn-sm btn-outline-danger" name="action" value="reject">Reject</button></div></form><?php endif; ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
