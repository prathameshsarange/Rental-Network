<?php
require_once __DIR__ . '/includes/db.php';

require_login();

$page_title = 'Edit Profile';
$errors = [];
$user = current_user($pdo);

if (!$user) {
    set_flash('danger', 'Your account could not be found. Please login again.');
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $locationAddress = trim($_POST['location_address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    if ($user['role'] === 'Admin') {
        $city = $user['city'];
        $state = $user['state'];
    }

    $requiredFields = [
        'Full name' => $name,
        'Phone' => $phone,
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

    if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors[] = 'Pincode must be exactly 6 digits.';
    }

    if (($latitude === '') !== ($longitude === '')) {
        $errors[] = 'Please select both latitude and longitude on the map.';
    } elseif ($latitude !== '' && !valid_coordinates($latitude, $longitude)) {
        $errors[] = 'Please select a valid location on the map.';
    }

    $profileImage = $user['profile_image'];
    if (empty($errors)) {
        [$uploadOk, $uploadedFilename, $uploadError] = handle_image_upload($_FILES['profile_image'] ?? [], 'profile_images', 'profile');

        if (!$uploadOk) {
            $errors[] = $uploadError;
        } elseif ($uploadedFilename !== null) {
            $profileImage = $uploadedFilename;
        }
    }

    if (empty($errors)) {
        try {
            $statement = $pdo->prepare("
                UPDATE users
                SET name = ?, phone = ?, address = ?, location_address = ?, city = ?, state = ?, pincode = ?, latitude = ?, longitude = ?, profile_image = ?
                WHERE user_id = ?
            ");
            $statement->execute([
                $name,
                $phone,
                $address,
                $locationAddress !== '' ? $locationAddress : null,
                $city,
                $state,
                $pincode,
                $latitude !== '' ? (float) $latitude : null,
                $longitude !== '' ? (float) $longitude : null,
                $profileImage,
                $user['user_id'],
            ]);

            $_SESSION['user_name'] = $name;
            if ($latitude !== '' && $longitude !== '') {
                notify_user_of_nearby_items($pdo, (int) $user['user_id']);
            }
            set_flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } catch (Throwable $exception) {
            error_log('Profile update error: ' . $exception->getMessage());
            $errors[] = 'Profile could not be updated. Please try again.';
        }
    }
}

$include_leaflet = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Edit Profile</h1>
                    <p class="text-muted mb-0">Update your contact and location details.</p>
                </div>
                <a href="<?php echo e(base_url('profile.php')); ?>" class="btn btn-outline-secondary btn-sm fw-medium shadow-sm">
                    <span aria-hidden="true">←</span> Back to Profile
                </a>
            </div>

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger border-0 bg-danger text-white mb-4 shadow-sm rounded-3">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form method="post" enctype="multipart/form-data" novalidate>
                        <!-- Profile Image Section -->
                        <div class="d-flex align-items-center gap-4 mb-5 pb-4 border-bottom border-subtle">
                            <div class="position-relative">
                                <?php if (!empty($user['profile_image'])) : ?>
                                    <img src="<?php echo e(base_url('uploads/profile_images/' . $user['profile_image'])); ?>" alt="Profile Image" class="rounded-circle object-fit-cover shadow-sm border border-2 border-white" style="width: 100px; height: 100px; background-color: var(--surface-alt);">
                                <?php else : ?>
                                    <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center fs-2 shadow-sm border border-2 border-white" style="width: 100px; height: 100px; background: var(--brand-emerald);">
                                        <?php echo e(strtoupper(substr($user['name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold mb-2">Profile Picture</h2>
                                <p class="text-muted small mb-3">Upload a new avatar. JPG, PNG or WebP under 2MB.</p>
                                <input type="file" class="form-control form-control-sm bg-surface-alt w-auto" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <h2 class="h5 fw-bold mb-4">Personal Details</h2>
                        <div class="row g-4 mb-5 pb-4 border-bottom border-subtle">
                            <div class="col-md-6">
                                <label for="name" class="form-label required fw-medium">Full Name</label>
                                <input type="text" class="form-control bg-surface-alt" id="name" name="name" value="<?php echo old('name', $user['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-medium">Email Address</label>
                                <input type="email" class="form-control bg-light text-muted" id="email" value="<?php echo e($user['email']); ?>" disabled>
                                <div class="form-text small">Email is used for login and cannot be changed here.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label required fw-medium">Phone Number</label>
                                <input type="tel" class="form-control bg-surface-alt" id="phone" name="phone" value="<?php echo old('phone', $user['phone']); ?>" pattern="[0-9]{10}" required>
                            </div>
                        </div>

                        <!-- Location Details -->
                        <h2 class="h5 fw-bold mb-4">Location Details</h2>
                        <div class="row g-4 mb-5">
                            <div class="col-12">
                                <label for="address" class="form-label required fw-medium">Street Address</label>
                                <textarea class="form-control bg-surface-alt" id="address" name="address" rows="3" required><?php echo old('address', $user['address']); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label required fw-medium">City</label>
                                <input type="text" class="form-control bg-surface-alt" id="city" name="city" value="<?php echo old('city', $user['city']); ?>" <?php echo $user['role'] === 'Admin' ? 'readonly class="form-control bg-light text-muted"' : ''; ?> required>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label required fw-medium">State</label>
                                <input type="text" class="form-control bg-surface-alt" id="state" name="state" value="<?php echo old('state', $user['state']); ?>" <?php echo $user['role'] === 'Admin' ? 'readonly class="form-control bg-light text-muted"' : ''; ?> required>
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label required fw-medium">Pincode</label>
                                <input type="text" class="form-control bg-surface-alt" id="pincode" name="pincode" value="<?php echo old('pincode', $user['pincode']); ?>" pattern="[0-9]{6}" required>
                            </div>
                            <div class="col-12">
                                <div class="location-picker" data-location-picker data-default-latitude="<?php echo e((string) LOCATION_DEFAULT_LATITUDE); ?>" data-default-longitude="<?php echo e((string) LOCATION_DEFAULT_LONGITUDE); ?>">
                                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                        <div><h3 class="h6 mb-1">Saved location for nearby items</h3><p class="small text-muted mb-0">Private: it is used for distance calculations and nearby alerts, not displayed on your public profile.</p></div>
                                        <button type="button" class="btn btn-outline-brand btn-sm" data-location-current-button>Use My Current Location</button>
                                    </div>
                                    <div class="input-group mb-3"><input type="text" class="form-control" data-location-search placeholder="Search location, e.g. Nagpur, Maharashtra"><button type="button" class="btn btn-outline-brand" data-location-search-button>Search</button></div>
                                    <div class="location-map" data-location-map aria-label="Interactive saved location map"></div>
                                    <label for="location_address" class="form-label fw-medium mt-3">Saved Location Address <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="text" class="form-control bg-surface-alt" id="location_address" name="location_address" data-location-address value="<?php echo old('location_address', $user['location_address'] ?? ''); ?>" placeholder="Select a point to receive nearby item alerts">
                                    <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Latitude</label><input class="form-control bg-surface-alt" name="latitude" data-location-latitude value="<?php echo old('latitude', $user['latitude'] ?? ''); ?>" readonly></div><div class="col-md-6"><label class="form-label">Longitude</label><input class="form-control bg-surface-alt" name="longitude" data-location-longitude value="<?php echo old('longitude', $user['longitude'] ?? ''); ?>" readonly></div></div>
                                    <div data-location-message class="location-picker-message mt-2 small">Saving a location enables alerts for new rental items within <?php echo e((string) NEARBY_RADIUS_KM); ?> KM.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-end gap-3 mt-4 pt-4 border-top border-subtle">
                            <a href="<?php echo e(base_url('profile.php')); ?>" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
                            <button type="submit" class="btn btn-brand px-4 py-2 shadow-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
