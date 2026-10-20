<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$page_title = 'My Items';

$statement = $pdo->prepare("
    SELECT
        i.item_id,
        i.item_name,
        i.description,
        i.rental_price,
        i.security_deposit,
        i.image,
        i.quantity,
        i.location,
        i.status,
        i.is_approved,
        i.created_at,
        c.category_name,
        COALESCE(b.total_bookings, 0) AS total_bookings
    FROM items i
    INNER JOIN categories c ON c.category_id = i.category_id
    LEFT JOIN (
        SELECT item_id, COUNT(*) AS total_bookings
        FROM bookings
        GROUP BY item_id
    ) b ON b.item_id = i.item_id
    WHERE i.owner_id = ?
      AND i.is_approved = 1
    ORDER BY i.created_at DESC
");
$statement->execute([current_user_id()]);
$items = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid max-w-1400 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-5 border-bottom border-subtle pb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">My Items</h1>
            <p class="text-muted mb-0">Manage the items you have listed for rent.</p>
        </div>
        <a href="<?php echo e(base_url('items/add_item.php')); ?>" class="btn btn-brand shadow-sm">
            <span aria-hidden="true">+</span> List New Item
        </a>
    </div>

    <?php if (empty($items)) : ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center p-5">
                <div class="display-1 text-muted opacity-25 mb-3">📦</div>
                <h2 class="h4 fw-bold">No items listed yet</h2>
                <p class="text-muted mb-4">Add your first rental item and start earning today.</p>
                <a href="<?php echo e(base_url('items/add_item.php')); ?>" class="btn btn-brand btn-lg">List an Item</a>
            </div>
        </div>
    <?php else : ?>
        <div class="row g-4">
            <?php foreach ($items as $item) : ?>
                <?php $imageUrl = item_image_url($item['image']); ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift transition-all">
                        <div class="position-relative" style="height: 200px;">
                            <?php if ($imageUrl !== null) : ?>
                                <img src="<?php echo e($imageUrl); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo e($item['item_name']); ?>">
                            <?php else : ?>
                                <div class="w-100 h-100 bg-surface-alt d-flex align-items-center justify-content-center text-muted fw-medium fs-5">
                                    <?php echo e($item['category_name']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="position-absolute top-0 end-0 m-3 d-flex flex-column gap-2">
                                <span class="badge badge-<?php echo e(strtolower(str_replace(' ', '-', $item['status'])) === 'available' ? 'available' : 'rented'); ?> px-2 py-1 rounded-pill shadow-sm">
                                    <?php echo e($item['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-4 d-flex flex-column">
                            <div class="mb-3">
                                <h2 class="h5 fw-bold mb-1 text-dark text-truncate" title="<?php echo e($item['item_name']); ?>"><?php echo e($item['item_name']); ?></h2>
                                <div class="d-flex align-items-center gap-2 text-muted small">
                                    <span class="badge bg-surface-alt border border-subtle text-dark"><?php echo e($item['category_name']); ?></span>
                                    <span>📍 <?php echo e(explode(',', $item['location'])[0]); ?></span>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-end justify-content-between mb-4">
                                <div>
                                    <span class="fs-4 fw-bold text-dark"><?php echo e(format_money($item['rental_price'])); ?></span>
                                    <span class="text-muted small">/day</span>
                                </div>
                                <div class="text-end small text-muted">
                                    <div>Dep: <?php echo e(format_money($item['security_deposit'])); ?></div>
                                    <div>Qty: <?php echo e($item['quantity']); ?></div>
                                </div>
                            </div>

                            <div class="bg-surface-alt rounded-3 p-2 mb-4 d-flex justify-content-between align-items-center text-muted small">
                                <span><span class="fw-medium text-dark"><?php echo e($item['total_bookings']); ?></span> Bookings</span>
                                <?php if (!(bool) $item['is_approved']) : ?>
                                    <span class="text-warning fw-medium"><span aria-hidden="true">⚠️</span> Hidden</span>
                                <?php else : ?>
                                    <span class="text-success fw-medium"><span aria-hidden="true">✓</span> Approved</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex flex-column gap-2 mt-auto">
                                <a href="<?php echo e(base_url('items/item_details.php?id=' . $item['item_id'])); ?>" class="btn btn-outline-secondary w-100">View Public Listing</a>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo e(base_url('items/edit_item.php?id=' . $item['item_id'])); ?>" class="btn btn-brand flex-fill">Edit</a>
                                    <a href="<?php echo e(base_url('items/delete_item.php?id=' . $item['item_id'])); ?>" class="btn btn-outline-danger flex-fill">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.max-w-1400 { max-width: 1400px; margin: 0 auto; }
.hover-lift:hover { transform: translateY(-4px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
