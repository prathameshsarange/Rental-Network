<?php
require_once __DIR__ . '/../includes/db.php';

$itemId = (int) ($_GET['id'] ?? 0);

if ($itemId < 1) {
    set_flash('danger', 'Invalid item selected.');
    redirect('items/browse_items.php');
}

$statement = $pdo->prepare("
    SELECT
        i.*,
        c.category_name,
        u.name AS owner_name,
        u.email AS owner_email,
        u.phone AS owner_phone,
        u.city AS owner_city,
        u.state AS owner_state,
        u.profile_image AS owner_profile_image,
        COALESCE(r.average_rating, 0) AS average_rating,
        COALESCE(r.total_reviews, 0) AS total_reviews
    FROM items i
    INNER JOIN categories c ON c.category_id = i.category_id
    INNER JOIN users u ON u.user_id = i.owner_id
    LEFT JOIN (
        SELECT item_id, AVG(rating) AS average_rating, COUNT(*) AS total_reviews
        FROM reviews
        GROUP BY item_id
    ) r ON r.item_id = i.item_id
    WHERE i.item_id = ?
    LIMIT 1
");
$statement->execute([$itemId]);
$item = $statement->fetch();

if (!$item) {
    set_flash('danger', 'Item not found.');
    redirect('items/browse_items.php');
}

$canSeeHiddenItem = is_logged_in() && ((int) $item['owner_id'] === current_user_id() || current_user_role() === 'Admin');

if (!(bool) $item['is_approved'] && !$canSeeHiddenItem) {
    set_flash('danger', 'This item is not available.');
    redirect('items/browse_items.php');
}

$page_title = $item['item_name'];
$imageUrl = item_image_url($item['image']);
$ownerImageUrl = uploaded_image_url('profile_images', $item['owner_profile_image']);
$isOwner = is_logged_in() && (int) $item['owner_id'] === current_user_id();
$isAvailable = $item['status'] === 'Available' && (bool) $item['is_approved'];
$hasMapLocation = valid_coordinates($item['latitude'] ?? null, $item['longitude'] ?? null);
if ($hasMapLocation) {
    [$mapLatitude, $mapLongitude] = approximate_map_coordinates((float) $item['latitude'], (float) $item['longitude']);
}

$reviewsStatement = $pdo->prepare("
    SELECT r.rating, r.review_text, r.created_at, u.name AS reviewer_name
    FROM reviews r
    INNER JOIN users u ON u.user_id = r.reviewer_id
    WHERE r.item_id = ?
    ORDER BY r.created_at DESC
    LIMIT 8
");
$reviewsStatement->execute([$itemId]);
$reviews = $reviewsStatement->fetchAll();

$similarStatement = $pdo->prepare("
    SELECT item_id, item_name, rental_price, location, status, image
    FROM items
    WHERE category_id = ?
      AND item_id <> ?
      AND is_approved = 1
    ORDER BY created_at DESC
    LIMIT 3
");
$similarStatement->execute([$item['category_id'], $itemId]);
$similarItems = $similarStatement->fetchAll();

$include_leaflet = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <!-- Breadcrumbs / Top Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="text-brand text-decoration-none">Items</a></li>
                <li class="breadcrumb-item"><a href="<?php echo e(base_url('items/browse_items.php?category_id='.$item['category_id'])); ?>" class="text-brand text-decoration-none"><?php echo e($item['category_name']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo e(substr($item['item_name'], 0, 30)); ?>...</li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <button class="btn btn-tertiary shadow-sm bg-surface">
                <span aria-hidden="true">📤</span> Share
            </button>
            <button class="btn btn-tertiary shadow-sm bg-surface">
                <span aria-hidden="true">♡</span> Save
            </button>
            <?php if ($isOwner) : ?>
                <a href="<?php echo e(base_url('items/edit_item.php?id=' . $item['item_id'])); ?>" class="btn btn-outline-brand">Edit Item</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Title Section -->
    <div class="mb-4">
        <h1 class="display-5 fw-bold mb-2"><?php echo e($item['item_name']); ?></h1>
        <div class="d-flex flex-wrap align-items-center gap-3 text-muted">
            <div class="d-flex align-items-center text-brand fw-semibold">
                <span class="me-1">★</span> <?php echo e(number_format((float) $item['average_rating'], 1)); ?> 
                <span class="text-muted ms-1 fw-normal">(<?php echo e($item['total_reviews']); ?> reviews)</span>
            </div>
            <span>•</span>
            <div><span aria-hidden="true">📍</span> <?php echo e($item['location']); ?></div>
            <span>•</span>
            <span class="badge badge-<?php echo e(strtolower($item['status']) == 'available' ? 'available' : 'rented'); ?>">
                <?php echo e($item['status']); ?>
            </span>
        </div>
    </div>

    <div class="row g-5">
        <!-- Left Column: Gallery, Details, Reviews -->
        <div class="col-lg-7 col-xl-8">
            <!-- Image Gallery -->
            <div class="card border-0 rounded-4 overflow-hidden shadow-sm mb-5">
                <?php if ($imageUrl !== null) : ?>
                    <img src="<?php echo e($imageUrl); ?>" class="w-100 item-detail-image" style="height: 500px;" alt="<?php echo e($item['item_name']); ?>">
                <?php else : ?>
                    <div class="d-flex align-items-center justify-content-center bg-surface-alt text-brand" style="height: 500px; font-size: 3rem;">
                        <?php echo e($item['category_name']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description & Specs -->
            <div class="mb-5">
                <h2 class="h4 fw-bold border-bottom border-subtle pb-3 mb-4">About this item</h2>
                <p class="text-muted fs-5 mb-4" style="white-space: pre-line;"><?php echo e($item['description']); ?></p>

                <div class="row g-4 mt-2">
                    <div class="col-sm-6">
                        <div class="d-flex gap-3">
                            <div class="fs-4 text-brand">✨</div>
                            <div>
                                <div class="fw-semibold text-primary">Condition</div>
                                <div class="text-muted"><?php echo e($item['item_condition']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3">
                            <div class="fs-4 text-brand">🏷️</div>
                            <div>
                                <div class="fw-semibold text-primary">Brand / Model</div>
                                <div class="text-muted"><?php echo e($item['brand'] ?: 'Not specified'); ?> <?php echo e($item['model'] ? '('.$item['model'].')' : ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3">
                            <div class="fs-4 text-brand">📦</div>
                            <div>
                                <div class="fw-semibold text-primary">Quantity Available</div>
                                <div class="text-muted"><?php echo e($item['quantity']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3">
                            <div class="fs-4 text-brand">🛡️</div>
                            <div>
                                <div class="fw-semibold text-primary">Security Deposit</div>
                                <div class="text-muted"><?php echo e(format_money($item['security_deposit'])); ?> (Fully refundable)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($hasMapLocation) : ?>
                <section class="mb-5 pt-4 border-top border-subtle">
                    <h2 class="h4 fw-bold mb-2">Pickup area</h2>
                    <p class="text-muted mb-3">📍 <?php echo e($item['location_address'] ?: $item['location']); ?> <span class="small d-block mt-1">The pin shows an approximate area to protect the owner's privacy.</span></p>
                    <div class="item-location-map" data-item-location-map data-latitude="<?php echo e((string) $mapLatitude); ?>" data-longitude="<?php echo e((string) $mapLongitude); ?>" aria-label="Approximate item location map"></div>
                </section>
            <?php else : ?>
                <section class="mb-5 pt-4 border-top border-subtle"><h2 class="h4 fw-bold mb-2">Pickup area</h2><p class="text-muted mb-0">📍 <?php echo e($item['location']); ?></p></section>
            <?php endif; ?>

            <!-- Owner Info -->
            <div class="mb-5 pt-4 border-top border-subtle">
                <h2 class="h4 fw-bold mb-4">Meet the owner</h2>
                <div class="d-flex align-items-start gap-4">
                    <?php if ($ownerImageUrl !== null) : ?>
                        <img src="<?php echo e($ownerImageUrl); ?>" alt="<?php echo e($item['owner_name']); ?>" class="rounded-circle object-fit-cover shadow-sm" style="width: 72px; height: 72px;">
                    <?php else : ?>
                        <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center fs-3 shadow-sm" style="width: 72px; height: 72px; background: var(--brand-emerald);">
                            <?php echo e(strtoupper(substr($item['owner_name'], 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="h5 fw-bold mb-1"><?php echo e($item['owner_name']); ?></h3>
                        <p class="text-muted small mb-3">📍 <?php echo e($item['owner_city']); ?>, <?php echo e($item['owner_state']); ?></p>
                        <a href="<?php echo e(base_url('profile.php?id=' . $item['owner_id'])); ?>" class="btn btn-outline-brand">View Profile</a>
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="mb-5 pt-4 border-top border-subtle">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <h2 class="h4 fw-bold mb-0">Reviews</h2>
                    <span class="badge bg-surface-alt text-brand fs-6 border border-subtle pt-1 pb-1">
                        ★ <?php echo e(number_format((float) $item['average_rating'], 1)); ?> (<?php echo e($item['total_reviews']); ?>)
                    </span>
                </div>

                <?php if (empty($reviews)) : ?>
                    <div class="p-4 bg-surface-alt rounded-3 text-muted text-center">
                        No reviews have been added for this item yet. Be the first to try it out!
                    </div>
                <?php else : ?>
                    <div class="row g-4">
                        <?php foreach ($reviews as $review) : ?>
                            <div class="col-md-6">
                                <div class="card h-100 border-subtle shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; background: var(--brand-dark);">
                                                <?php echo e(strtoupper(substr($review['reviewer_name'], 0, 1))); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?php echo e($review['reviewer_name']); ?></div>
                                                <div class="small text-muted"><?php echo e(date('M Y', strtotime($review['created_at']))); ?></div>
                                            </div>
                                        </div>
                                        <div class="text-brand mb-2">
                                            <?php echo str_repeat('★', (int) $review['rating']); ?><span class="text-muted"><?php echo str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                                        </div>
                                        <p class="text-muted mb-0 small"><?php echo e($review['review_text']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Sticky Booking Panel -->
        <div class="col-lg-5 col-xl-4">
            <div class="card shadow-lg border-0 rounded-4 sticky-top" style="top: 100px; z-index: 1000;">
                <div class="card-body p-4">
                    <div class="mb-4 pb-4 border-bottom border-subtle">
                        <?php if ($item['rental_unit'] !== 'Hourly') : ?><div><span class="fs-2 fw-bold text-primary">₹<?php echo e(number_format((float) $item['rental_price'], 2)); ?></span><span class="text-muted fs-5"> / day</span></div><?php endif; ?>
                        <?php if ($item['rental_unit'] !== 'Daily') : ?><div class="<?php echo $item['rental_unit'] === 'Both' ? 'mt-2' : ''; ?>"><span class="fs-3 fw-bold text-primary">₹<?php echo e(number_format((float) $item['hourly_price'], 2)); ?></span><span class="text-muted fs-6"> / hour</span></div><?php endif; ?>
                    </div>

                    <?php if ($isOwner) : ?>
                        <div class="alert alert-info border-0 bg-surface-alt text-brand">
                            <div class="d-flex gap-2 align-items-center">
                                <span>👋</span> This is your own item.
                            </div>
                        </div>
                        <a href="<?php echo e(base_url('items/edit_item.php?id=' . $item['item_id'])); ?>" class="btn btn-outline-brand w-100 py-3 mt-3">Manage Listing</a>
                    <?php elseif (!$isAvailable) : ?>
                        <div class="alert alert-warning border-0">
                            Currently unavailable for booking.
                        </div>
                    <?php elseif (is_logged_in()) : ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Check Availability</label>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control" title="Start Date">
                                <input type="date" class="form-control" title="End Date">
                            </div>
                            <small class="text-muted mt-2 d-block text-center">Dates are for preview only. Select on next screen.</small>
                        </div>
                        <a href="<?php echo e(base_url('booking/book_item.php?item_id=' . $item['item_id'])); ?>" class="btn btn-brand btn-lg w-100 py-3 shadow-sm" style="font-size: 1.1rem;">Request to Rent</a>
                        <p class="text-center text-muted small mt-3 mb-0">You won't be charged yet</p>
                    <?php else : ?>
                        <a href="<?php echo e(base_url('login.php')); ?>" class="btn btn-brand btn-lg w-100 py-3">Login to Book</a>
                    <?php endif; ?>
                </div>
                
                <!-- Trust Badges -->
                <div class="card-footer bg-surface-alt border-0 p-4 text-center">
                    <div class="d-flex justify-content-center gap-4 text-muted small">
                        <div>
                            <div class="fs-4 mb-1">🛡️</div>
                            Secure Payments
                        </div>
                        <div>
                            <div class="fs-4 mb-1">🤝</div>
                            Verified Users
                        </div>
                    </div>
                    <div class="mt-4 border-top border-subtle pt-3">
                        <button class="btn btn-link text-muted text-decoration-none small p-0"><span aria-hidden="true">🚩</span> Report this listing</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
