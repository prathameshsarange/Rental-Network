<?php
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Categories';

$statement = $pdo->query("
    SELECT
        c.category_id,
        c.category_name,
        c.description,
        COUNT(i.item_id) AS available_items
    FROM categories c
    LEFT JOIN items i
        ON i.category_id = c.category_id
        AND i.is_approved = 1
        AND i.status = 'Available'
    WHERE c.status = 'Active'
    GROUP BY c.category_id, c.category_name, c.description
    ORDER BY c.category_name
");
$categories = $statement->fetchAll();

$categoryImages = [
    'Books' => 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?auto=format&fit=crop&w=900&q=80',
    'Cameras' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80',
    'Electronics' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=900&q=80',
    'Furniture' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=80',
    'Gaming' => 'https://images.unsplash.com/photo-1607853202273-797f1c22a38e?auto=format&fit=crop&w=900&q=80',
    'Laptops' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80',
    'Musical Instruments' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=900&q=80',
    'Party Equipment' => 'https://images.unsplash.com/photo-1506157786151-b8491531f063?auto=format&fit=crop&w=900&q=80',
    'Sports Equipment' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=80',
    'Tools' => 'https://images.unsplash.com/photo-1530124566582-a618bc2615dc?auto=format&fit=crop&w=900&q=80',
    'Vehicles' => 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=80',
];
$fallbackCategoryImage = 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=900&q=80';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Categories</h1>
    <p class="text-muted mb-0">Choose a category to find items available for rent.</p>
</div>

<?php if (empty($categories)) : ?>
    <div class="card shadow-sm">
        <div class="card-body text-center p-5">
            <h2 class="h4">No categories available</h2>
            <p class="text-muted mb-0">Please check back shortly.</p>
        </div>
    </div>
<?php else : ?>
    <div class="row g-3">
        <?php foreach ($categories as $category) : ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a class="card category-card category-visual-card h-100 text-decoration-none" href="<?php echo e(base_url('items/browse_items.php?category_id=' . $category['category_id'])); ?>">
                    <img class="category-card-image" src="<?php echo e($categoryImages[$category['category_name']] ?? $fallbackCategoryImage); ?>" alt="<?php echo e($category['category_name']); ?> rentals">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5 text-dark mb-2"><?php echo e($category['category_name']); ?></h2>
                        <p class="text-muted small mb-3"><?php echo e($category['description']); ?></p>
                        <span class="small text-brand mt-auto"><?php echo e($category['available_items']); ?> available item<?php echo $category['available_items'] == 1 ? '' : 's'; ?></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
