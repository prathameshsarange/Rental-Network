<?php
require_once __DIR__ . '/includes/db.php';

require_guest();

$page_title = 'Reset Password';
$errors = [];
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$validUser = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $statement = $pdo->prepare("SELECT user_id, name FROM users WHERE reset_token = ? AND reset_expires >= NOW() AND status = 'Active' LIMIT 1");
    $statement->execute([$tokenHash]);
    $validUser = $statement->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$validUser) {
        $errors[] = 'This password reset link is invalid or expired.';
    }

    $passwordError = password_strength_error($password);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?');
        $update->execute([$hashedPassword, $validUser['user_id']]);

        set_flash('success', 'Password reset successful. Please login.');
        redirect('login.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper m-n4">
    <div class="auth-image-side">
        <div class="position-absolute top-0 start-0 p-4 z-1">
            <a href="<?php echo e(base_url('index.php')); ?>" class="text-white text-decoration-none fw-bold fs-5 d-flex align-items-center gap-2">
                <span class="brand-mark bg-white text-brand" aria-hidden="true">R</span> Rental Network
            </a>
        </div>
        <img src="https://images.unsplash.com/photo-1550005973-540ee06470ce?auto=format&fit=crop&w=1200&q=80" alt="Reset Password">
        <div class="position-absolute bottom-0 start-0 w-100 p-5 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
            <h2 class="h3 fw-bold mb-2">Secure your account</h2>
            <p class="mb-0 fs-5 text-white-50">Create a strong password to keep your rentals and personal information safe.</p>
        </div>
    </div>
    <div class="auth-form-side">
        <div class="auth-form-container">
            <!-- Mobile Logo -->
            <div class="d-lg-none mb-4 pb-3 border-bottom border-subtle">
                <a href="<?php echo e(base_url('index.php')); ?>" class="text-brand fw-bold fs-5 text-decoration-none d-flex align-items-center gap-2">
                    <span class="brand-mark" aria-hidden="true">R</span> Rental Network
                </a>
            </div>

            <h1 class="fw-bold">Create New Password</h1>
            <p>Choose a strong password for your account.</p>

            <?php if (!$validUser && $token === '') : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">Password reset token is missing.</div>
            <?php elseif (!$validUser) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">This password reset link is invalid or expired.</div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($validUser) : ?>
                <form method="post" novalidate class="mt-4">
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label required">New Password</label>
                        <input type="password" class="form-control form-control-lg bg-surface-alt" id="password" name="password" data-password required placeholder="Enter new password">
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label required">Confirm New Password</label>
                        <input type="password" class="form-control form-control-lg bg-surface-alt" id="confirm_password" name="confirm_password" data-confirm-password required placeholder="Repeat new password">
                    </div>
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-brand btn-lg py-3 shadow-sm">Reset Password</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="<?php echo e(base_url('login.php')); ?>" class="text-muted text-decoration-none fw-semibold">
                    <span aria-hidden="true">←</span> Back to login
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.site-main > .container {
    max-width: 100% !important;
    padding: 0 !important;
}
.site-navbar, footer {
    display: none !important;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
