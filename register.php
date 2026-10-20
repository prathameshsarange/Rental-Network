<?php
require_once __DIR__ . '/includes/db.php';

require_guest();

$page_title = 'Register';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    $requiredFields = [
        'Full name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Password' => $password,
        'Confirm password' => $confirmPassword,
        'Address' => $address,
        'City' => $city,
        'State' => $state,
        'Pincode' => $pincode,
    ];

    foreach ($requiredFields as $label => $value) {
        if ($value === '') {
            $errors[] = $label . ' is required.';
        }
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors[] = 'Pincode must be exactly 6 digits.';
    }

    $passwordError = password_strength_error($password);
    if ($passwordError !== null) {
        $errors[] = $passwordError;
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (empty($errors)) {
        $statement = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);

        if ($statement->fetch()) {
            $errors[] = 'This email address is already registered.';
        }
    }

    $profileImage = null;
    if (empty($errors)) {
        [$uploadOk, $uploadedFilename, $uploadError] = handle_image_upload($_FILES['profile_image'] ?? [], 'profile_images', 'profile');

        if (!$uploadOk) {
            $errors[] = $uploadError;
        } else {
            $profileImage = $uploadedFilename;
        }
    }

    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $statement = $pdo->prepare("
                INSERT INTO users
                    (name, email, phone, password, address, city, state, pincode, profile_image, role, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'User', 'Active')
            ");

            $statement->execute([
                $name,
                $email,
                $phone,
                $hashedPassword,
                $address,
                $city,
                $state,
                $pincode,
                $profileImage,
            ]);

            set_flash('success', 'Registration successful! Please login.');
            redirect('login.php');
        } catch (Throwable $exception) {
            error_log('Registration error: ' . $exception->getMessage());
            $errors[] = 'Registration could not be completed. Please try again.';
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
        <img src="https://images.unsplash.com/photo-1542204165-65bf26472b9b?auto=format&fit=crop&w=1200&q=80" alt="Rental Community">
        <div class="position-absolute bottom-0 start-0 w-100 p-5 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
            <h2 class="h3 fw-bold mb-2">Join a sustainable community</h2>
            <p class="mb-0 fs-5 text-white-50">List your underused items or rent exactly what you need. Less waste, more value.</p>
        </div>
    </div>
    <div class="auth-form-side" style="overflow-y: auto;">
        <div class="auth-form-container" style="max-width: 600px; padding: 2rem 0;">
            <!-- Mobile Logo -->
            <div class="d-lg-none mb-4 pb-3 border-bottom border-subtle">
                <a href="<?php echo e(base_url('index.php')); ?>" class="text-brand fw-bold fs-5 text-decoration-none d-flex align-items-center gap-2">
                    <span class="brand-mark" aria-hidden="true">R</span> Rental Network
                </a>
            </div>

            <h1 class="fw-bold">Create Account</h1>
            <p>Join Rental Network to rent items or list your own.</p>

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" novalidate class="mt-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label required">Full Name</label>
                        <input type="text" class="form-control bg-surface-alt" id="name" name="name" value="<?php echo old('name'); ?>" placeholder="E.g. Priya Sharma" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label required">Email</label>
                        <input type="email" class="form-control bg-surface-alt" id="email" name="email" value="<?php echo old('email'); ?>" placeholder="you@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label required">Phone</label>
                        <input type="tel" class="form-control bg-surface-alt" id="phone" name="phone" value="<?php echo old('phone'); ?>" pattern="[0-9]{10}" placeholder="10-digit number" required>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_image" class="form-label">Profile Image <span class="text-muted fw-normal">(Optional)</span></label>
                        <input type="file" class="form-control bg-surface-alt py-2" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label required">Password</label>
                        <input type="password" class="form-control bg-surface-alt" id="password" name="password" data-password placeholder="Create a password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label required">Confirm Password</label>
                        <input type="password" class="form-control bg-surface-alt" id="confirm_password" name="confirm_password" data-confirm-password placeholder="Repeat password" required>
                    </div>
                    
                    <div class="col-12 mt-4 pt-3 border-top border-subtle">
                        <h3 class="h6 fw-bold mb-3">Location Details</h3>
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label required">Address</label>
                        <textarea class="form-control bg-surface-alt" id="address" name="address" rows="2" placeholder="Street address, apt, suite" required><?php echo old('address'); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="city" class="form-label required">City</label>
                        <input type="text" class="form-control bg-surface-alt" id="city" name="city" value="<?php echo old('city'); ?>" placeholder="E.g. Pune" required>
                    </div>
                    <div class="col-md-4">
                        <label for="state" class="form-label required">State</label>
                        <input type="text" class="form-control bg-surface-alt" id="state" name="state" value="<?php echo old('state'); ?>" placeholder="E.g. Maharashtra" required>
                    </div>
                    <div class="col-md-4">
                        <label for="pincode" class="form-label required">Pincode</label>
                        <input type="text" class="form-control bg-surface-alt" id="pincode" name="pincode" value="<?php echo old('pincode'); ?>" pattern="[0-9]{6}" placeholder="E.g. 411001" required>
                    </div>
                </div>

                <div class="d-grid mt-5">
                    <button type="submit" class="btn btn-brand btn-lg py-3 shadow-sm">Create Account</button>
                </div>
            </form>

            <div class="text-center mt-4 pb-4">
                <p class="text-muted">Already have an account? <a href="<?php echo e(base_url('login.php')); ?>" class="text-brand text-decoration-none fw-semibold">Sign in</a></p>
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
