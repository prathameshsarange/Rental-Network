<?php
require_once __DIR__ . '/includes/db.php';

require_login();

$page_title = 'Change Password';
$errors = [];
$user = current_user($pdo);

if (!$user) {
    set_flash('danger', 'Your account could not be found. Please login again.');
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'All password fields are required.';
    }

    if ($currentPassword !== '' && !password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    }

    $passwordError = password_strength_error($newPassword);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirm password do not match.';
    }

    if ($currentPassword !== '' && $newPassword !== '' && $currentPassword === $newPassword) {
        $errors[] = 'New password must be different from current password.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $statement = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $statement->execute([$hashedPassword, $user['user_id']]);

        set_flash('success', 'Password changed successfully.');
        redirect('dashboard.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card auth-card-narrow shadow-sm">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Change Password</h1>
        <p class="text-muted mb-4">Update your account password.</p>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="mb-3">
                <label for="current_password" class="form-label required">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label required">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" data-password required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label required">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" data-confirm-password required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-brand">Change Password</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
