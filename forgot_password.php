<?php
require_once __DIR__ . '/includes/db.php';

require_guest();

$page_title = 'Forgot Password';
$errors = [];
$successMessage = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $statement = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND status = 'Active' LIMIT 1");
        $statement->execute([$email]);
        $user = $statement->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $update = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?');
            $update->execute([$tokenHash, $expiresAt, $user['user_id']]);

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = 'http://' . $host . base_url('reset_password.php?token=' . urlencode($token));
            $successMessage = 'Demo reset link generated. It expires in 30 minutes.';
        } else {
            $successMessage = 'If this email exists, a password reset option will be available.';
        }
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
        <img src="https://images.unsplash.com/photo-1518458028785-8fbcd101ebb9?auto=format&fit=crop&w=1200&q=80" alt="Forgot Password">
        <div class="position-absolute bottom-0 start-0 w-100 p-5 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
            <h2 class="h3 fw-bold mb-2">We've got you covered</h2>
            <p class="mb-0 fs-5 text-white-50">It happens to the best of us. Let's get you back into your account securely.</p>
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

            <h1 class="fw-bold">Reset Password</h1>
            <p>Generate a secure link to reset your account password.</p>

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== '') : ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis mb-4">
                    <?php echo e($successMessage); ?>
                    <?php if ($resetLink !== '') : ?>
                        <div class="mt-3">
                            <a class="btn btn-brand" href="<?php echo e($resetLink); ?>">Open Reset Link</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate class="mt-4">
                <div class="mb-4">
                    <label for="email" class="form-label required">Registered Email</label>
                    <input type="email" class="form-control form-control-lg bg-surface-alt" id="email" name="email" value="<?php echo old('email'); ?>" placeholder="Enter your email address" required>
                </div>
                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-brand btn-lg py-3 shadow-sm">Send Reset Link</button>
                </div>
            </form>

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
