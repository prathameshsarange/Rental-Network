<?php
require_once __DIR__ . '/includes/db.php';

require_guest();

$page_title = 'Login';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $errors[] = 'Your account is blocked. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            set_flash('success', 'Login successful.');
            redirect('dashboard.php');
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
        <img src="https://images.unsplash.com/photo-1550005973-540ee06470ce?auto=format&fit=crop&w=1200&q=80" alt="Rental Equipment">
        <div class="position-absolute bottom-0 start-0 w-100 p-5 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
            <h2 class="h3 fw-bold mb-2">Welcome back to your community</h2>
            <p class="mb-0 fs-5 text-white-50">Manage your rentals, discover new items, and earn from your unused belongings.</p>
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

            <h1 class="fw-bold">Welcome back</h1>
            <p>Please enter your details to sign in.</p>

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate class="mt-4">
                <div class="mb-3">
                    <label for="email" class="form-label required">Email</label>
                    <input type="email" class="form-control form-control-lg bg-surface-alt" id="email" name="email" value="<?php echo old('email'); ?>" placeholder="Enter your email" required>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label required mb-0">Password</label>
                        <a href="<?php echo e(base_url('forgot_password.php')); ?>" class="text-brand text-decoration-none small fw-semibold">Forgot password?</a>
                    </div>
                    <input type="password" class="form-control form-control-lg bg-surface-alt" id="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-brand btn-lg py-3 shadow-sm">Sign in</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="text-muted">Don't have an account? <a href="<?php echo e(base_url('register.php')); ?>" class="text-brand text-decoration-none fw-semibold">Sign up</a></p>
            </div>
        </div>
    </div>
</div>

<style>
/* Override default container padding for auth pages */
.site-main > .container {
    max-width: 100% !important;
    padding: 0 !important;
}
.site-navbar, footer {
    display: none !important;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
