<?php
require_once __DIR__ . '/includes/db.php';

require_login();

$profileId = isset($_GET['id']) ? (int) $_GET['id'] : current_user_id();
$isOwnProfile = $profileId === current_user_id();

$statement = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
$statement->execute([$profileId]);
$profileUser = $statement->fetch();

if (!$profileUser) {
    set_flash('danger', 'Profile not found.');
    redirect('dashboard.php');
}

if ($profileUser['status'] !== 'Active' && !$isOwnProfile && current_user_role() !== 'Admin') {
    set_flash('danger', 'This profile is not available.');
    redirect('dashboard.php');
}

$page_title = $isOwnProfile ? 'My Profile' : $profileUser['name'] . ' Profile';

$itemsListed = dashboard_count($pdo, 'SELECT COUNT(*) FROM items WHERE owner_id = ?', [$profileId]);
$completedRentals = dashboard_count(
    $pdo,
    "SELECT COUNT(*) FROM bookings WHERE booking_status = 'Completed' AND (renter_id = ? OR owner_id = ?)",
    [$profileId, $profileId]
);

$ratingStatement = $pdo->prepare('SELECT AVG(rating) AS average_rating, COUNT(*) AS total_reviews FROM reviews WHERE owner_id = ?');
$ratingStatement->execute([$profileId]);
$ratingSummary = $ratingStatement->fetch() ?: ['average_rating' => 0, 'total_reviews' => 0];

$reviewsStatement = $pdo->prepare("
    SELECT r.rating, r.review_text, r.created_at, i.item_name, u.name AS reviewer_name
    FROM reviews r
    INNER JOIN items i ON i.item_id = r.item_id
    INNER JOIN users u ON u.user_id = r.reviewer_id
    WHERE r.owner_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewsStatement->execute([$profileId]);
$reviews = $reviewsStatement->fetchAll();

$itemsStatement = $pdo->prepare("
    SELECT i.item_id, i.item_name, i.rental_price, i.status, i.location, c.category_name
    FROM items i
    INNER JOIN categories c ON c.category_id = i.category_id
    WHERE i.owner_id = ?
    ORDER BY i.created_at DESC
    LIMIT 6
");
$itemsStatement->execute([$profileId]);
$listedItems = $itemsStatement->fetchAll();

$profileImagePath = '';
if (!empty($profileUser['profile_image'])) {
    $profileImagePath = 'uploads/profile_images/' . $profileUser['profile_image'];
}

$averageRating = (float) ($ratingSummary['average_rating'] ?? 0);
$totalReviews = (int) ($ratingSummary['total_reviews'] ?? 0);
$initials = strtoupper(substr($profileUser['name'], 0, 1));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <!-- Breadcrumb & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo e(base_url('dashboard.php')); ?>" class="text-brand text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo e($isOwnProfile ? 'My Profile' : 'User Profile'); ?></li>
            </ol>
        </nav>
        <?php if ($isOwnProfile) : ?>
            <div class="d-flex gap-2">
                <a href="<?php echo e(base_url('edit_profile.php')); ?>" class="btn btn-outline-brand">Edit Profile</a>
                <a href="<?php echo e(base_url('change_password.php')); ?>" class="btn btn-outline-secondary">Password</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-5">
        <!-- Sidebar: User Info -->
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden mb-4">
                <div class="bg-brand w-100" style="height: 100px; background: linear-gradient(135deg, var(--brand-emerald), var(--brand-dark));"></div>
                <div class="card-body px-4 pb-4 pt-0 text-center">
                    <div class="position-relative d-inline-block" style="margin-top: -50px; margin-bottom: 1rem;">
                        <?php if ($profileImagePath !== '') : ?>
                            <img src="<?php echo e(base_url($profileImagePath)); ?>" alt="<?php echo e($profileUser['name']); ?>" class="rounded-circle object-fit-cover border border-4 border-white shadow-sm" style="width: 100px; height: 100px; background-color: #fff;">
                        <?php else : ?>
                            <div class="rounded-circle border border-4 border-white shadow-sm bg-surface-alt text-brand d-flex align-items-center justify-content-center fw-bold display-6 mx-auto" style="width: 100px; height: 100px; background-color: #fff;">
                                <?php echo e($initials); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($profileUser['status'] === 'Active'): ?>
                            <span class="position-absolute bottom-0 end-0 p-2 bg-success border border-light rounded-circle" title="Active">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="h4 fw-bold mb-1"><?php echo e($profileUser['name']); ?></h1>
                    <p class="text-muted mb-3">📍 <?php echo e($profileUser['city']); ?>, <?php echo e($profileUser['state']); ?></p>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <span class="badge bg-surface-alt text-dark border border-subtle"><?php echo e($profileUser['role']); ?></span>
                        <span class="badge badge-<?php echo e(strtolower($profileUser['status']) === 'active' ? 'available' : 'rented'); ?>"><?php echo e($profileUser['status']); ?></span>
                    </div>

                    <div class="text-start border-top border-subtle pt-4">
                        <h2 class="h6 fw-bold mb-3 text-uppercase text-muted" style="letter-spacing: 0.5px;">Contact Info</h2>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                            <li class="d-flex align-items-start gap-3">
                                <span class="text-muted">✉️</span>
                                <span class="text-break"><?php echo e($profileUser['email']); ?></span>
                            </li>
                            <li class="d-flex align-items-start gap-3">
                                <span class="text-muted">📞</span>
                                <span><?php echo e($profileUser['phone']); ?></span>
                            </li>
                            <li class="d-flex align-items-start gap-3">
                                <span class="text-muted">🏠</span>
                                <?php if ($isOwnProfile) : ?>
                                    <span><?php echo e($profileUser['address']); ?><br><?php echo e($profileUser['pincode']); ?></span>
                                <?php else : ?>
                                    <span><?php echo e($profileUser['city']); ?>, <?php echo e($profileUser['state']); ?><br><small class="text-muted">Exact address is private.</small></span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-8 col-xl-9">
            <!-- Stats -->
            <div class="row g-4 mb-5">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface-alt">
                        <div class="card-body p-4 text-center">
                            <div class="display-5 fw-bold text-dark mb-1"><?php echo e($itemsListed); ?></div>
                            <div class="text-muted fw-medium">Items Listed</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface-alt">
                        <div class="card-body p-4 text-center">
                            <div class="display-5 fw-bold text-dark mb-1"><?php echo e($completedRentals); ?></div>
                            <div class="text-muted fw-medium">Completed Rentals</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface-alt">
                        <div class="card-body p-4 text-center">
                            <div class="display-5 fw-bold text-dark mb-1 d-flex align-items-center justify-content-center gap-1">
                                <span class="text-brand fs-4">★</span> <?php echo e(number_format($averageRating, 1)); ?>
                            </div>
                            <div class="text-muted fw-medium"><?php echo e($totalReviews); ?> Reviews</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="card border-0 shadow-sm rounded-4 mb-5">
                <div class="card-header bg-transparent border-bottom border-subtle d-flex justify-content-between align-items-center p-4">
                    <h2 class="h5 fw-bold mb-0">Listed Items</h2>
                    <?php if ($isOwnProfile) : ?>
                        <a href="<?php echo e(base_url('items/add_item.php')); ?>" class="btn btn-sm btn-brand">Add Item</a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($listedItems)) : ?>
                        <div class="p-5 text-center text-muted">
                            <p class="mb-0">No items listed yet.</p>
                        </div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 custom-table">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold">Item</th>
                                        <th class="py-3 fw-semibold">Category</th>
                                        <th class="py-3 fw-semibold">Price</th>
                                        <th class="py-3 fw-semibold">Status</th>
                                        <th class="pe-4 py-3 fw-semibold text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="border-top-0">
                                    <?php foreach ($listedItems as $item) : ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-semibold text-dark"><?php echo e($item['item_name']); ?></div>
                                                <div class="small text-muted"><?php echo e($item['location']); ?></div>
                                            </td>
                                            <td class="py-3 text-muted"><?php echo e($item['category_name']); ?></td>
                                            <td class="py-3 text-dark fw-medium">₹<?php echo e(number_format((float) $item['rental_price'], 2)); ?> <span class="text-muted fw-normal small">/day</span></td>
                                            <td class="py-3">
                                                <span class="badge badge-<?php echo e(strtolower(str_replace(' ', '-', $item['status'])) === 'available' ? 'available' : 'rented'); ?> px-2 py-1 rounded-pill fw-medium">
                                                    <?php echo e($item['status']); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 py-3 text-end">
                                                <a href="<?php echo e(base_url('items/item_details.php?id=' . $item['item_id'])); ?>" class="btn btn-sm btn-light text-brand fw-medium">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-bottom border-subtle p-4">
                    <h2 class="h5 fw-bold mb-0">Recent Reviews</h2>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($reviews)) : ?>
                        <div class="text-center text-muted p-4">
                            <p class="mb-0">No reviews yet.</p>
                        </div>
                    <?php else : ?>
                        <div class="d-flex flex-column gap-4">
                            <?php foreach ($reviews as $review) : ?>
                                <div class="border-bottom border-subtle pb-4 last-no-border">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?php echo e(strtoupper(substr($review['reviewer_name'], 0, 1))); ?>
                                            </div>
                                            <strong class="text-dark"><?php echo e($review['reviewer_name']); ?></strong>
                                            <span class="text-muted small">• <?php echo e(date('M d, Y', strtotime($review['created_at']))); ?></span>
                                        </div>
                                        <div class="text-brand bg-surface-alt px-2 py-1 rounded small">
                                            <?php echo str_repeat('★', (int) $review['rating']); ?><span class="text-muted opacity-50"><?php echo str_repeat('★', 5 - (int) $review['rating']); ?></span>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-dark"><?php echo e($review['review_text']); ?></p>
                                    <p class="small text-muted mb-0">
                                        Item: <span class="fw-medium text-dark"><?php echo e($review['item_name']); ?></span>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
.custom-table th { font-size: 0.75rem; letter-spacing: 0.5px; }
.custom-table td { vertical-align: middle; }
.last-no-border:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
