<?php
require_once __DIR__ . '/includes/db.php';

$page_title = 'Home';

$categories = [];
$featuredItems = [];

try {
    $categoriesStatement = $pdo->query("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name LIMIT 10");
    $categories = $categoriesStatement->fetchAll();

    $itemsStatement = $pdo->query("
        SELECT
            i.item_id,
            i.item_name,
            i.rental_price,
            i.location,
            i.image,
            c.category_name,
            COALESCE(r.average_rating, 0) AS average_rating,
            COALESCE(r.total_reviews, 0) AS total_reviews
        FROM items i
        INNER JOIN categories c ON c.category_id = i.category_id
        LEFT JOIN (
            SELECT item_id, AVG(rating) AS average_rating, COUNT(*) AS total_reviews
            FROM reviews
            GROUP BY item_id
        ) r ON r.item_id = i.item_id
        WHERE i.status = 'Available' AND i.is_approved = 1 AND c.status = 'Active'
        ORDER BY i.created_at DESC
        LIMIT 6
    ");
    $featuredItems = $itemsStatement->fetchAll();
} catch (Throwable $exception) {
    $categories = [];
    $featuredItems = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">Rent What You Need.<br>Share What You Own.</h1>
        <p class="hero-subtitle">Discover useful things from people around you — affordable, convenient and built around your community.</p>

        <form class="search-bar-widget" action="<?php echo e(base_url('items/browse_items.php')); ?>" method="get">
            <input type="search" name="search" placeholder="What are you looking for?" aria-label="Search">
            <div class="search-divider"></div>
            <input type="text" name="location" placeholder="Where? (e.g., Ranchi)" aria-label="Location">
            <div class="search-divider"></div>
            <select name="category_id" aria-label="Category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo e($category['category_id']); ?>"><?php echo e($category['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-brand">Search</button>
        </form>
    </div>
</div>

<div class="container pb-section">
    <!-- Popular Categories -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0">Popular Categories</h2>
        <a href="<?php echo e(base_url('categories/categories.php')); ?>" class="btn btn-tertiary">View All</a>
    </div>
    <div class="row g-4 mb-5">
        <?php 
        $icons = ['📷', '💻', '🚗', '🛋️', '⚽', '🛠️', '🎮', '🎉', '🎸', '🏕️'];
        foreach ($categories as $index => $category) : ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?php echo e(base_url('items/browse_items.php?category_id=' . $category['category_id'])); ?>" class="category-tile h-100">
                    <div class="category-icon"><?php echo $icons[$index % count($icons)]; ?></div>
                    <span class="text-center"><?php echo e($category['category_name']); ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Popular Near You -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-4">
        <h2 class="h3 mb-0">Popular Near You</h2>
        <a href="<?php echo e(base_url('items/browse_items.php')); ?>" class="btn btn-outline-brand">Explore More</a>
    </div>

    <div class="item-grid mb-5">
        <?php if (empty($featuredItems)) : ?>
            <div class="col-12">
                <div class="alert alert-info bg-surface-alt border-0 text-brand">No featured items found yet. Be the first to list!</div>
            </div>
        <?php endif; ?>

        <?php foreach ($featuredItems as $item) : ?>
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
                </div>
                <div class="item-meta">
                    <div>
                        <h3 class="item-title"><?php echo e($item['item_name']); ?></h3>
                        <p class="item-location mb-0"><?php echo e($item['location']); ?></p>
                    </div>
                    <div class="item-rating">
                        <span>★</span> <?php echo e(number_format((float) $item['average_rating'], 1)); ?>
                    </div>
                </div>
                <div class="item-price">
                    ₹<?php echo e(number_format((float) $item['rental_price'], 2)); ?> <span class="text-muted fw-normal">/ day</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- How It Works -->
    <div class="pt-5 pb-4">
        <h2 class="h3 text-center mb-5">How Rental Network Works</h2>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="mb-3">
                    <span class="badge bg-surface-alt text-brand rounded-circle p-3 fs-5">01</span>
                </div>
                <h4 class="h5">Discover</h4>
                <p class="text-muted">Find exactly what you need from people in your local community.</p>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <span class="badge bg-surface-alt text-brand rounded-circle p-3 fs-5">02</span>
                </div>
                <h4 class="h5">Request & Pay</h4>
                <p class="text-muted">Choose your dates, send a request, and pay securely (demo).</p>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <span class="badge bg-surface-alt text-brand rounded-circle p-3 fs-5">03</span>
                </div>
                <h4 class="h5">Pick Up & Return</h4>
                <p class="text-muted">Meet up to collect the item, enjoy it, and return it when done.</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="card bg-surface-alt border-0 mt-5 text-center p-5 rounded-4">
        <div class="card-body">
            <h2 class="h3 mb-3">Have something sitting unused?</h2>
            <p class="text-muted mb-4 lead">List it on Rental Network and start earning while helping your community.</p>
            <a href="<?php echo e(base_url('items/my_items.php')); ?>" class="btn btn-brand btn-lg px-5">List an Item</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
