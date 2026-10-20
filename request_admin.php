<?php
require_once __DIR__ . '/includes/db.php';

require_login();

$user = current_user($pdo);
if (!$user || $user['role'] !== 'User') {
    set_flash('warning', 'Only normal user accounts can submit an admin access request.');
    redirect('dashboard.php');
}

$requestStatement = $pdo->prepare('SELECT request_id, request_message, request_status, review_note, created_at, reviewed_at FROM admin_requests WHERE user_id = ? LIMIT 1');
$requestStatement->execute([current_user_id()]);
$existingRequest = $requestStatement->fetch();
$page_title = 'Request Admin Access';
$requestMessage = trim($_POST['request_message'] ?? ($existingRequest['request_message'] ?? ''));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($existingRequest && $existingRequest['request_status'] === 'Pending') {
        $errors[] = 'Your admin access request is already pending.';
    } elseif (strlen($requestMessage) > 500) {
        $errors[] = 'Your request message must be 500 characters or less.';
    } else {
        $statement = $pdo->prepare("INSERT INTO admin_requests (user_id, request_message, request_status, reviewed_by, review_note, reviewed_at) VALUES (?, ?, 'Pending', NULL, NULL, NULL) ON DUPLICATE KEY UPDATE request_message = VALUES(request_message), request_status = 'Pending', reviewed_by = NULL, review_note = NULL, reviewed_at = NULL, created_at = CURRENT_TIMESTAMP");
        $statement->execute([current_user_id(), $requestMessage ?: null]);
        set_flash('success', 'Your admin access request has been sent for review.');
        redirect('request_admin.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4 mb-2">Request Admin Access</h1><p class="text-muted">Admin access is reviewed for your city and state. Only one active admin account is allowed per city and state.</p>
<?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($existingRequest && $existingRequest['request_status'] === 'Pending') : ?><div class="alert alert-info mb-0">Your request is pending review. Submitted on <?php echo e(date('d M Y, h:i A', strtotime($existingRequest['created_at']))); ?>.</div><?php else : ?><form method="post"><div class="location-summary mb-3"><strong>Location for this request</strong><span><?php echo e($user['city']); ?>, <?php echo e($user['state']); ?></span></div><?php if ($existingRequest && $existingRequest['request_status'] === 'Rejected') : ?><div class="alert alert-warning">Your previous request was not approved. <?php echo e($existingRequest['review_note'] ?: 'You may submit a new request.'); ?></div><?php endif; ?><div class="mb-4"><label class="form-label" for="request_message">Message for the reviewer</label><textarea class="form-control" id="request_message" name="request_message" rows="5" maxlength="500" placeholder="Tell the reviewer why you need admin access."><?php echo e($requestMessage); ?></textarea></div><button class="btn btn-brand" type="submit">Send Request</button></form><?php endif; ?></div></div></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
