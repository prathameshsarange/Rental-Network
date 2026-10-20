<?php
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Browse Items';

$categoriesStatement = $pdo->query("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name");
$categories = $categoriesStatement->fetchAll();

$allowedConditions = ['New', 'Like New', 'Good', 'Fair'];
$allowedStatuses = ['Available', 'Unavailable', 'Rented', 'Maintenance'];
$allowedSorts = [
    'newest' => 'i.created_at DESC',
    'price_low' => 'i.rental_price ASC',
    'price_high' => 'i.rental_price DESC',
    'highest_rated' => 'average_rating DESC, total_reviews DESC, i.created_at DESC',
];

$search = trim($_GET['search'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$location = trim($_GET['location'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$availability = trim($_GET['availability'] ?? '');
$sort = $_GET['sort'] ?? '';
$currentUser = is_logged_in() ? current_user($pdo) : null;
$canSortByDistance = $currentUser && valid_coordinates($currentUser['latitude'] ?? null, $currentUser['longitude'] ?? null);

// For a searched category such as "bike", local results should naturally start with the closest listing.
if ($sort === '') {
    $sort = $canSortByDistance && ($search !== '' || $categoryId > 0 || $location !== '') ? 'nearest' : 'newest';
}

$where = ["i.is_approved = 1", "c.status = 'Active'"];
$params = [];

if ($search !== '') {
    $where[] = '(i.item_name LIKE ? OR i.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $categoryId;
}

if ($location !== '') {
    $where[] = '(i.location LIKE ? OR i.city LIKE ? OR i.state LIKE ?)';
    $params[] = '%' . $location . '%';
    $params[] = '%' . $location . '%';
    $params[] = '%' . $location . '%';
}

if ($brand !== '') {
    $where[] = 'i.brand LIKE ?';
    $params[] = '%' . $brand . '%';
}

if ($minPrice !== '' && is_numeric($minPrice)) {
    $where[] = 'i.rental_price >= ?';
    $params[] = (float) $minPrice;
}

if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $where[] = 'i.rental_price <= ?';
    $params[] = (float) $maxPrice;
}

if (in_array($condition, $allowedConditions, true)) {
    $where[] = 'i.item_condition = ?';
    $params[] = $condition;
}

if (in_array($availability, $allowedStatuses, true)) {
    $where[] = 'i.status = ?';
    $params[] = $availability;
}

$orderBy = $allowedSorts[$sort] ?? $allowedSorts['newest'];

$sql = "
    SELECT
        i.item_id,
        i.owner_id,
        i.item_name,
        i.description,
        i.brand,
        i.item_condition,
        i.rental_price,
        i.security_deposit,
        i.image,
        i.location,
        i.location_address,
        i.latitude,
        i.longitude,
        i.status,
        i.created_at,
        c.category_name,
        u.name AS owner_name,
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
    WHERE " . implode(' AND ', $where) . "
    ORDER BY " . $orderBy;

$statement = $pdo->prepare($sql);
$statement->execute($params);
$items = $statement->fetchAll();

if ($canSortByDistance) {
    foreach ($items as &$item) {
        $item['distance_km'] = valid_coordinates($item['latitude'] ?? null, $item['longitude'] ?? null)
            ? haversine_distance_km((float) $currentUser['latitude'], (float) $currentUser['longitude'], (float) $item['latitude'], (float) $item['longitude'])
            : null;
    }
    unset($item);

    if ($sort === 'nearest') {
        usort($items, static function (array $first, array $second): int {
            if ($first['distance_km'] === null) {
                return 1;
            }
            if ($second['distance_km'] === null) {
                return -1;
            }
            return $first['distance_km'] <=> $second['distance_km'];
        });
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4 max-w-1400">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold">Browse Items</h1>
            <p class="text-muted mb-0">Search by item name, then see the closest matching listing first.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-lg-0">
            <?php if (is_logged_in()) : ?>
                <a href="<?php echo e(base_url('items/nearby_items.php')); ?>" class="btn btn-outline-brand">Nearby Items</a>
                <a href="<?php echo e(base_url('items/locations.php')); ?>" class="btn btn-outline-brand">Browse Locations</a>
                <a href="<?php echo e(base_url('items/add_item.php')); ?>" class="btn btn-brand">List Your Item</a>
            <?php else : ?>
                <a href="<?php echo e(base_url('items/locations.php')); ?>" class="btn btn-outline-brand">Browse Locations</a>
                <a href="<?php echo e(base_url('login.php')); ?>" class="btn btn-brand">Login to List</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Filters -->
        <aside class="col-lg-3 col-xl-3">
            <div class="card border-0 shadow-sm sticky-top" style="top: 100px; z-index: 1000;">
                <div class="card-header bg-transparent border-bottom border-subtle d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold fs-6">Filters</h5>
                    <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="text-muted small text-decoration-none">Clear all</a>
                </div>
                <div class="card-body">
                    <form method="get" id="filterForm">
                        <div class="mb-4">
                            <label for="search" class="form-label fw-semibold">Search</label>
                            <input type="search" class="form-control bg-surface-alt" id="search" name="search" value="<?php echo e($search); ?>" placeholder="E.g., Camera, laptop">
                        </div>
                        <div class="mb-4">
                            <label for="category_id" class="form-label fw-semibold">Category</label>
                            <select class="form-select bg-surface-alt" id="category_id" name="category_id">
                                <option value="0">All categories</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo e($category['category_id']); ?>" <?php echo ($categoryId === (int) $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo e($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="location" class="form-label fw-semibold">Location</label>
                            <input type="text" class="form-control bg-surface-alt" id="location" name="location" value="<?php echo e($location); ?>" placeholder="E.g., Pune, Mumbai">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Price Range (₹/day)</label>
                            <div class="d-flex gap-2">
                                <input type="number" class="form-control bg-surface-alt" name="min_price" value="<?php echo e($minPrice); ?>" min="0" placeholder="Min">
                                <input type="number" class="form-control bg-surface-alt" name="max_price" value="<?php echo e($maxPrice); ?>" min="0" placeholder="Max">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="brand" class="form-label fw-semibold">Brand</label>
                            <input type="text" class="form-control bg-surface-alt" id="brand" name="brand" value="<?php echo e($brand); ?>" placeholder="E.g., Sony, Bosch">
                        </div>
                        <div class="mb-4">
                            <label for="condition" class="form-label fw-semibold">Condition</label>
                            <select class="form-select bg-surface-alt" id="condition" name="condition">
                                <option value="">Any condition</option>
                                <?php foreach ($allowedConditions as $conditionOption) : ?>
                                    <option value="<?php echo e($conditionOption); ?>" <?php echo ($condition === $conditionOption) ? 'selected' : ''; ?>><?php echo e($conditionOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="availability" class="form-label fw-semibold">Availability</label>
                            <select class="form-select bg-surface-alt" id="availability" name="availability">
                                <option value="">Any status</option>
                                <?php foreach ($allowedStatuses as $statusOption) : ?>
                                    <option value="<?php echo e($statusOption); ?>" <?php echo ($availability === $statusOption) ? 'selected' : ''; ?>><?php echo e($statusOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Mobile-only apply button (Optional layout) -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-brand">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Product Grid -->
        <div class="col-lg-9 col-xl-9">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-subtle">
                <p class="text-muted mb-0 fw-medium"><?php echo e(count($items)); ?> item(s) found</p>
                <div class="d-flex align-items-center gap-2">
                    <label for="sort-desktop" class="text-muted small mb-0 text-nowrap">Sort by:</label>
                    <select class="form-select form-select-sm border-0 bg-surface-alt fw-semibold" id="sort-desktop" form="filterForm" name="sort" onchange="document.getElementById('filterForm').submit()">
                        <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo ($sort === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo ($sort === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="highest_rated" <?php echo ($sort === 'highest_rated') ? 'selected' : ''; ?>>Highest Rated</option>
                        <?php if ($canSortByDistance) : ?><option value="nearest" <?php echo ($sort === 'nearest') ? 'selected' : ''; ?>>Nearest to me</option><?php endif; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($items)) : ?>
                <div class="alert alert-info bg-surface-alt border-0 text-center py-5 rounded-4">
                    <div class="fs-1 mb-3">🔍</div>
                    <h3 class="h5 fw-bold text-brand mb-2">No items found</h3>
                    <p class="text-muted mb-0">Try adjusting your filters or search terms to find what you're looking for.</p>
                </div>
            <?php else : ?>
                <div class="item-grid">
                    <?php foreach ($items as $item) : ?>
                        <?php $imageUrl = item_image_url($item['image']); ?>
                        <a href="<?php echo e(base_url('items/item_details.php?id=' . $item['item_id'])); ?>" class="market-card">
                            <div class="card-img-top-wrapper">
                                <?php if ($imageUrl !== null) : ?>
                                    <img src="<?php echo e($imageUrl); ?>" class="card-img-top" alt="<?php echo e($item['item_name']); ?>">
                                <?php else : ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-surface-alt text-brand fw-bold">
                                        <?php echo e($item['category_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="fav-btn" onclick="event.preventDefault();"><span aria-hidden="true">♡</span></div>
                                <?php if ($item['status'] !== 'Available'): ?>
                                    <div class="position-absolute bottom-0 start-0 w-100 p-2" style="background: linear-gradient(transparent, rgba(0,0,0,0.7)); z-index: 1;">
                                        <span class="badge badge-rented text-uppercase shadow-sm">
                                            <?php echo e($item['status']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-meta">
                                <div>
                                    <h3 class="item-title text-truncate" style="max-width: 200px;" title="<?php echo e($item['item_name']); ?>"><?php echo e($item['item_name']); ?></h3>
                                    <p class="item-location mb-0"><?php echo e($item['location']); ?> • <?php echo e($item['item_condition']); ?></p>
                                </div>
                                <div class="item-rating text-brand">
                                    <span>★</span> <?php echo e(number_format((float) $item['average_rating'], 1)); ?>
                                </div>
                            </div>
                            <?php if ($canSortByDistance && $item['distance_km'] !== null) : ?>
                                <div class="item-distance">Near you: <?php echo e(number_format((float) $item['distance_km'], 1)); ?> KM away</div>
                            <?php endif; ?>
                            <div class="item-price mt-2">
                                ₹<?php echo e(number_format((float) $item['rental_price'], 2)); ?> <span class="text-muted fw-normal text-sm">/ day</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
