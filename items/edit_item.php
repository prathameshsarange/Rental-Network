<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$itemId = (int) ($_GET['id'] ?? 0);

if ($itemId < 1) {
    set_flash('danger', 'Invalid item selected.');
    redirect('items/my_items.php');
}

$itemStatement = $pdo->prepare('SELECT * FROM items WHERE item_id = ? AND owner_id = ? LIMIT 1');
$itemStatement->execute([$itemId, current_user_id()]);
$item = $itemStatement->fetch();

if (!$item) {
    set_flash('danger', 'Item not found or you are not allowed to edit it.');
    redirect('items/my_items.php');
}

$page_title = 'Edit Item';
$errors = [];

$categoriesStatement = $pdo->query("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name");
$categories = $categoriesStatement->fetchAll();

$allowedConditions = ['New', 'Like New', 'Good', 'Fair'];
$allowedStatuses = ['Available', 'Unavailable', 'Rented', 'Maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['item_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $condition = trim($_POST['item_condition'] ?? '');
    $rentalPrice = trim($_POST['rental_price'] ?? '');
    $rentalUnit = trim($_POST['rental_unit'] ?? 'Daily');
    $hourlyPrice = trim($_POST['hourly_price'] ?? '');
    $securityDeposit = trim($_POST['security_deposit'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $locationAddress = trim($_POST['location_address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');

    $requiredFields = [
        'Item name' => $itemName,
        'Description' => $description,
        'Category' => $categoryId,
        'Condition' => $condition,
        'Security deposit' => $securityDeposit,
        'Quantity' => $quantity,
        'Location' => $location,
        'Selected map address' => $locationAddress,
        'Map latitude' => $latitude,
        'Map longitude' => $longitude,
        'City' => $city,
        'State' => $state,
        'Pincode' => $pincode,
        'Availability status' => $status,
    ];

    foreach ($requiredFields as $label => $value) {
        if ($value === '' || $value === 0) {
            $errors[] = $label . ' is required.';
        }
    }

    if ($categoryId > 0) {
        $categoryCheck = $pdo->prepare("SELECT category_id FROM categories WHERE category_id = ? AND status = 'Active'");
        $categoryCheck->execute([$categoryId]);
        if (!$categoryCheck->fetch()) {
            $errors[] = 'Please select a valid active category.';
        }
    }

    if (!in_array($condition, $allowedConditions, true)) {
        $errors[] = 'Please select a valid item condition.';
    }

    if (!in_array($rentalUnit, ['Daily', 'Hourly', 'Both'], true)) {
        $errors[] = 'Please select a valid rental mode.';
    }

    if (in_array($rentalUnit, ['Daily', 'Both'], true) && ($rentalPrice === '' || !is_numeric($rentalPrice) || (float) $rentalPrice <= 0)) {
        $errors[] = 'Daily rental price must be greater than zero.';
    }

    if (in_array($rentalUnit, ['Hourly', 'Both'], true) && ($hourlyPrice === '' || !is_numeric($hourlyPrice) || (float) $hourlyPrice <= 0)) {
        $errors[] = 'Hourly rental price must be greater than zero.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Please select a valid availability status.';
    }

    if ($securityDeposit !== '' && (!is_numeric($securityDeposit) || (float) $securityDeposit < 0)) {
        $errors[] = 'Security deposit cannot be negative.';
    }

    if ($quantity !== '' && (!ctype_digit($quantity) || (int) $quantity < 1)) {
        $errors[] = 'Quantity must be at least 1.';
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors[] = 'Pincode must be exactly 6 digits.';
    }

    if ($latitude !== '' && $longitude !== '' && !valid_coordinates($latitude, $longitude)) {
        $errors[] = 'Please select a valid location on the map.';
    }

    $itemImage = $item['image'];
    if (empty($errors)) {
        [$uploadOk, $uploadedFilename, $uploadError] = handle_image_upload($_FILES['item_image'] ?? [], 'item_images', 'item');

        if (!$uploadOk) {
            $errors[] = $uploadError;
        } elseif ($uploadedFilename !== null) {
            $itemImage = $uploadedFilename;
        }
    }

    if (empty($errors)) {
        try {
            $statement = $pdo->prepare("
                UPDATE items
                SET category_id = ?,
                    item_name = ?,
                    description = ?,
                    brand = ?,
                    model = ?,
                    item_condition = ?,
                    rental_price = ?,
                    rental_unit = ?,
                    hourly_price = ?,
                    security_deposit = ?,
                    image = ?,
                    quantity = ?,
                    location = ?,
                    location_address = ?,
                    city = ?,
                    state = ?,
                    pincode = ?,
                    latitude = ?,
                    longitude = ?,
                    status = ?
                WHERE item_id = ? AND owner_id = ?
            ");

            $statement->execute([
                $categoryId,
                $itemName,
                $description,
                $brand !== '' ? $brand : null,
                $model !== '' ? $model : null,
                $condition,
                (float) ($rentalPrice !== '' ? $rentalPrice : 0),
                $rentalUnit,
                $hourlyPrice !== '' ? (float) $hourlyPrice : null,
                (float) $securityDeposit,
                $itemImage,
                (int) $quantity,
                $location,
                $locationAddress,
                $city,
                $state,
                $pincode,
                (float) $latitude,
                (float) $longitude,
                $status,
                $itemId,
                current_user_id(),
            ]);

            set_flash('success', 'Item updated successfully.');
            redirect('items/my_items.php');
        } catch (Throwable $exception) {
            error_log('Edit item error: ' . $exception->getMessage());
            $errors[] = 'Item could not be updated. Please try again.';
        }
    }
}

$currentImageUrl = item_image_url($item['image']);

$include_leaflet = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Edit Item</h1>
                    <p class="text-muted mb-0"><?php echo e($item['item_name']); ?></p>
                </div>
                <a href="<?php echo e(base_url('items/my_items.php')); ?>" class="btn btn-outline-secondary btn-sm fw-medium shadow-sm">
                    <span aria-hidden="true">←</span> Back to My Items
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
                        
                        <!-- Basic Information -->
                        <h2 class="h5 fw-bold mb-4 pb-2 border-bottom border-subtle">1. Basic Information</h2>
                        <div class="row g-4 mb-5">
                            <div class="col-md-8">
                                <label for="item_name" class="form-label required fw-medium">Item Name</label>
                                <input type="text" class="form-control bg-surface-alt" id="item_name" name="item_name" value="<?php echo old('item_name', $item['item_name']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="category_id" class="form-label required fw-medium">Category</label>
                                <select class="form-select bg-surface-alt" id="category_id" name="category_id" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category) : ?>
                                        <?php $selectedCategory = (int) ($_POST['category_id'] ?? $item['category_id']); ?>
                                        <option value="<?php echo e($category['category_id']); ?>" <?php echo ($selectedCategory === (int) $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label required fw-medium">Description</label>
                                <textarea class="form-control bg-surface-alt" id="description" name="description" rows="4" required><?php echo old('description', $item['description']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="brand" class="form-label fw-medium">Brand</label>
                                <input type="text" class="form-control bg-surface-alt" id="brand" name="brand" value="<?php echo old('brand', $item['brand'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="model" class="form-label fw-medium">Model</label>
                                <input type="text" class="form-control bg-surface-alt" id="model" name="model" value="<?php echo old('model', $item['model'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Pricing & Status -->
                        <h2 class="h5 fw-bold mb-4 pb-2 border-bottom border-subtle">2. Pricing & Details</h2>
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label for="rental_unit" class="form-label required fw-medium">Rental Mode</label>
                                <?php $selectedRentalUnit = $_POST['rental_unit'] ?? ($item['rental_unit'] ?? 'Daily'); ?>
                                <select class="form-select bg-surface-alt" id="rental_unit" name="rental_unit" data-rental-mode required>
                                    <?php foreach (['Daily' => 'Per Day', 'Hourly' => 'Per Hour', 'Both' => 'Daily and Hourly'] as $unitValue => $unitLabel) : ?>
                                        <option value="<?php echo e($unitValue); ?>" <?php echo ($selectedRentalUnit === $unitValue) ? 'selected' : ''; ?>><?php echo e($unitLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4" data-daily-price-field>
                                <label for="rental_price" class="form-label fw-medium">Rental Price (₹ / Day)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-0">₹</span>
                                    <input type="number" class="form-control bg-surface-alt" id="rental_price" name="rental_price" value="<?php echo old('rental_price', $item['rental_price']); ?>" min="1" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4" data-hourly-price-field>
                                <label for="hourly_price" class="form-label fw-medium">Rental Price (₹ / Hour)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-0">₹</span>
                                    <input type="number" class="form-control bg-surface-alt" id="hourly_price" name="hourly_price" value="<?php echo old('hourly_price', $item['hourly_price'] ?? ''); ?>" min="1" step="0.01" placeholder="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="security_deposit" class="form-label required fw-medium">Security Deposit (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-0">₹</span>
                                    <input type="number" class="form-control bg-surface-alt" id="security_deposit" name="security_deposit" value="<?php echo old('security_deposit', $item['security_deposit']); ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="item_condition" class="form-label required fw-medium">Condition</label>
                                <select class="form-select bg-surface-alt" id="item_condition" name="item_condition" required>
                                    <option value="">Select condition</option>
                                    <?php foreach ($allowedConditions as $conditionOption) : ?>
                                        <?php $selectedCondition = $_POST['item_condition'] ?? $item['item_condition']; ?>
                                        <option value="<?php echo e($conditionOption); ?>" <?php echo ($selectedCondition === $conditionOption) ? 'selected' : ''; ?>>
                                            <?php echo e($conditionOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="quantity" class="form-label required fw-medium">Quantity Available</label>
                                <input type="number" class="form-control bg-surface-alt" id="quantity" name="quantity" value="<?php echo old('quantity', $item['quantity']); ?>" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label required fw-medium">Availability Status</label>
                                <select class="form-select bg-surface-alt" id="status" name="status" required>
                                    <?php foreach ($allowedStatuses as $statusOption) : ?>
                                        <?php $selectedStatus = $_POST['status'] ?? $item['status']; ?>
                                        <option value="<?php echo e($statusOption); ?>" <?php echo ($selectedStatus === $statusOption) ? 'selected' : ''; ?>>
                                            <?php echo e($statusOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Location & Media -->
                        <h2 class="h5 fw-bold mb-4 pb-2 border-bottom border-subtle">3. Location & Media</h2>
                        <div class="row g-4 mb-5">
                            <div class="col-12">
                                <label for="item_image" class="form-label fw-medium">Update Item Image <span class="text-muted fw-normal">(Optional)</span></label>
                                <div class="d-flex align-items-center gap-4">
                                    <?php if ($currentImageUrl !== null) : ?>
                                        <img src="<?php echo e($currentImageUrl); ?>" alt="Current Image" class="rounded-3 object-fit-cover shadow-sm border border-subtle" style="width: 80px; height: 80px;">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control bg-surface-alt" id="item_image" name="item_image" accept=".jpg,.jpeg,.png,.webp">
                                        <div class="form-text small mt-1">Leave empty to keep current image. Accepted formats: JPG, PNG, WebP.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="location" class="form-label required fw-medium">Rental Location</label>
                                <input type="text" class="form-control bg-surface-alt" id="location" name="location" value="<?php echo old('location', $item['location']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label required fw-medium">City</label>
                                <input type="text" class="form-control bg-surface-alt" id="city" name="city" value="<?php echo old('city', $item['city']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label required fw-medium">State</label>
                                <input type="text" class="form-control bg-surface-alt" id="state" name="state" value="<?php echo old('state', $item['state']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label required fw-medium">Pincode</label>
                                <input type="text" class="form-control bg-surface-alt" id="pincode" name="pincode" value="<?php echo old('pincode', $item['pincode']); ?>" pattern="[0-9]{6}" required>
                            </div>
                            <div class="col-12">
                                <div class="location-picker" data-location-picker data-default-latitude="<?php echo e((string) LOCATION_DEFAULT_LATITUDE); ?>" data-default-longitude="<?php echo e((string) LOCATION_DEFAULT_LONGITUDE); ?>">
                                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                        <div><h3 class="h6 mb-1">Item map location</h3><p class="small text-muted mb-0">Update the approximate pickup area. Exact coordinates stay private.</p></div>
                                        <button type="button" class="btn btn-outline-brand btn-sm" data-location-current-button>Use My Current Location</button>
                                    </div>
                                    <div class="input-group mb-3"><input type="text" class="form-control" data-location-search placeholder="Search location, e.g. Sitabuldi, Nagpur"><button type="button" class="btn btn-outline-brand" data-location-search-button>Search</button></div>
                                    <div class="location-map" data-location-map aria-label="Interactive item location map"></div>
                                    <label for="location_address" class="form-label required fw-medium mt-3">Selected Address</label>
                                    <input type="text" class="form-control bg-surface-alt" id="location_address" name="location_address" data-location-address value="<?php echo old('location_address', $item['location_address'] ?? $item['location']); ?>" required>
                                    <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Latitude</label><input class="form-control bg-surface-alt" name="latitude" data-location-latitude value="<?php echo old('latitude', $item['latitude'] ?? ''); ?>" readonly required></div><div class="col-md-6"><label class="form-label">Longitude</label><input class="form-control bg-surface-alt" name="longitude" data-location-longitude value="<?php echo old('longitude', $item['longitude'] ?? ''); ?>" readonly required></div></div>
                                    <div data-location-message class="location-picker-message mt-2 small">Search, click, or drag the pin to update the location.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-end gap-3 mt-4 pt-4 border-top border-subtle">
                            <a href="<?php echo e(base_url('items/my_items.php')); ?>" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
