<?php
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Rental Locations';
$statement = $pdo->query("SELECT city, state, COUNT(*) AS item_count FROM items WHERE is_approved = 1 AND status = 'Available' GROUP BY city, state ORDER BY item_count DESC, city ASC");
$locations = $statement->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Rental Locations</h1><p class="text-muted mb-0">Browse available items by city. No map or external service is needed.</p></div>
<?php if (!$locations) : ?><div class="alert alert-info">No rental locations are available yet.</div><?php else : ?><div class="row g-3"><?php foreach ($locations as $location) : ?><div class="col-sm-6 col-lg-4 col-xl-3"><a class="card location-card h-100 shadow-sm text-decoration-none" href="<?php echo e(base_url('items/browse_items.php?location=' . rawurlencode($location['city']))); ?>"><div class="card-body"><h2 class="h5 text-dark mb-1"><?php echo e($location['city']); ?></h2><p class="text-muted mb-3"><?php echo e($location['state']); ?></p><span class="text-brand small"><?php echo e($location['item_count']); ?> available item<?php echo $location['item_count'] == 1 ? '' : 's'; ?></span></div></a></div><?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
