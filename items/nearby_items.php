<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$page_title = 'Nearby Items';
$allowedRadii = [5, 10, 20, 50];
$radius = (int) ($_GET['radius'] ?? (int) NEARBY_RADIUS_KM);
if (!in_array($radius, $allowedRadii, true)) {
    $radius = (int) NEARBY_RADIUS_KM;
}

$user = current_user($pdo);
$nearbyItems = [];
$hasSavedLocation = $user && valid_coordinates($user['latitude'] ?? null, $user['longitude'] ?? null);

if ($hasSavedLocation) {
    $statement = $pdo->prepare("SELECT i.item_id, i.owner_id, i.item_name, i.rental_price, i.image, i.location, i.location_address, i.latitude, i.longitude, i.status, c.category_name
        FROM items i INNER JOIN categories c ON c.category_id = i.category_id
        WHERE i.is_approved = 1 AND i.status = 'Available' AND i.owner_id <> ? AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL");
    $statement->execute([current_user_id()]);
    foreach ($statement->fetchAll() as $item) {
        if (!valid_coordinates($item['latitude'], $item['longitude'])) {
            continue;
        }
        $item['distance_km'] = haversine_distance_km((float) $user['latitude'], (float) $user['longitude'], (float) $item['latitude'], (float) $item['longitude']);
        if ($item['distance_km'] <= $radius) {
            $nearbyItems[] = $item;
        }
    }
    usort($nearbyItems, static fn(array $first, array $second): int => $first['distance_km'] <=> $second['distance_km']);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid max-w-1400 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-4"><div><h1 class="h3 fw-bold mb-1">Nearby Rental Items</h1><p class="text-muted mb-0">Items near your saved location. Your precise location is never shown publicly.</p></div><a class="btn btn-outline-brand" href="<?php echo e(base_url('edit_profile.php')); ?>">Update Saved Location</a></div>
    <div class="d-flex flex-wrap gap-2 mb-4"><?php foreach ($allowedRadii as $option) : ?><a href="<?php echo e(base_url('items/nearby_items.php?radius=' . $option)); ?>" class="btn <?php echo $radius === $option ? 'btn-brand' : 'btn-outline-brand'; ?> btn-sm">Within <?php echo e((string) $option); ?> KM</a><?php endforeach; ?></div>
    <?php if (!$hasSavedLocation) : ?><div class="card border-0 shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">Save a location to see nearby items</h2><p class="text-muted mb-4">Choose a location in your profile to receive relevant local listings and notifications.</p><a class="btn btn-brand" href="<?php echo e(base_url('edit_profile.php')); ?>">Set Saved Location</a></div></div>
    <?php elseif (!$nearbyItems) : ?><div class="card border-0 shadow-sm"><div class="card-body text-center p-5"><h2 class="h4">No rental items found near your location.</h2><p class="text-muted mb-0">Try a larger radius or check again when new items are listed.</p></div></div>
    <?php else : ?>
        <div class="nearest-item-note"><strong>Closest option:</strong> <?php echo e($nearbyItems[0]['item_name']); ?> is <?php echo e(number_format((float) $nearbyItems[0]['distance_km'], 1)); ?> KM from your saved location. Other options are ordered from nearest to farthest.</div>
        <div class="item-grid"><?php foreach ($nearbyItems as $index => $item) : ?><?php $imageUrl = item_image_url($item['image']); ?><a href="<?php echo e(base_url('items/item_details.php?id=' . $item['item_id'])); ?>" class="market-card <?php echo $index === 0 ? 'nearest-item-card' : ''; ?>"><div class="card-img-top-wrapper"><?php if ($imageUrl) : ?><img src="<?php echo e($imageUrl); ?>" alt="<?php echo e($item['item_name']); ?>"><?php else : ?><div class="item-placeholder h-100"><?php echo e($item['category_name']); ?></div><?php endif; ?><?php if ($index === 0) : ?><span class="nearest-item-label">Closest to you</span><?php endif; ?></div><div class="item-meta"><div><h2 class="item-title"><?php echo e($item['item_name']); ?></h2><p class="item-location mb-0">📍 <?php echo e($item['location_address'] ?: $item['location']); ?></p></div><span class="badge text-bg-success"><?php echo e(number_format($item['distance_km'], 1)); ?> KM</span></div><div class="item-price">₹<?php echo e(number_format((float) $item['rental_price'], 2)); ?> <span class="text-muted fw-normal">/ day</span></div></a><?php endforeach; ?></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
