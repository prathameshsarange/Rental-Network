<?php
require_once __DIR__ . '/../includes/db.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId < 1 || $userId === current_user_id()) {
        set_flash('warning', 'This account cannot be changed here.');
    } elseif ($action === 'toggle_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['Active', 'Blocked'], true)) {
            $update = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'User'");
            $update->execute([$status, $userId]);
            set_flash('success', 'User status updated.');
        }
    } elseif ($action === 'promote') {
        $update = $pdo->prepare("UPDATE users SET role = 'Admin' WHERE user_id = ? AND role = 'User' AND status = 'Active'");
        $update->execute([$userId]);
        set_flash($update->rowCount() === 1 ? 'success' : 'warning', $update->rowCount() === 1 ? 'User promoted to admin successfully.' : 'Only active user accounts can be promoted.');
    }

    redirect('admin/users.php');
}

$page_title = 'Manage Users';
$users = $pdo->query('SELECT user_id, name, email, phone, city, state, role, status, created_at FROM users ORDER BY created_at DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Manage Users</h1>
        <p class="text-muted mb-0">Block or unblock normal user accounts. Admin access is handled through submitted requests.</p>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">User</th><th>Location</th><th>Role</th><th>Status</th><th class="pe-3 text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td class="ps-3"><strong><?php echo e($user['name']); ?></strong><div class="small text-muted"><?php echo e($user['email']); ?> | <?php echo e($user['phone']); ?></div></td>
                        <td><?php echo e($user['city']); ?>, <?php echo e($user['state']); ?></td>
                        <td><span class="badge text-bg-<?php echo $user['role'] === 'Admin' ? 'dark' : 'secondary'; ?>"><?php echo e($user['role']); ?></span></td>
                        <td><span class="badge text-bg-<?php echo $user['status'] === 'Active' ? 'success' : 'danger'; ?>"><?php echo e($user['status']); ?></span></td>
                        <td class="pe-3 text-end">
                            <?php if ($user['role'] === 'User') : ?>
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <form method="post"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?php echo e($user['user_id']); ?>"><input type="hidden" name="status" value="<?php echo $user['status'] === 'Active' ? 'Blocked' : 'Active'; ?>"><button class="btn btn-sm btn-outline-<?php echo $user['status'] === 'Active' ? 'danger' : 'success'; ?>"><?php echo $user['status'] === 'Active' ? 'Block' : 'Unblock'; ?></button></form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
