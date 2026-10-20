<?php
require_once __DIR__ . '/../includes/db.php';
$itemId = (int) ($_GET['item_id'] ?? 0);
if ($itemId < 1) { set_flash('warning', 'Select an item to view its reviews.'); redirect('items/browse_items.php'); }
$itemStatement = $pdo->prepare('SELECT item_id, item_name FROM items WHERE item_id = ? LIMIT 1'); $itemStatement->execute([$itemId]); $item = $itemStatement->fetch();
if (!$item) { set_flash('danger', 'Item not found.'); redirect('items/browse_items.php'); }
$statement = $pdo->prepare('SELECT r.rating, r.review_text, r.created_at, u.name AS reviewer_name FROM reviews r INNER JOIN users u ON u.user_id = r.reviewer_id WHERE r.item_id = ? ORDER BY r.created_at DESC'); $statement->execute([$itemId]); $reviews = $statement->fetchAll();
$average = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0; $page_title = 'Reviews'; require_once __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="h3 mb-1">Reviews for <?php echo e($item['item_name']); ?></h1><p class="text-muted mb-0">Average rating: <?php echo e(number_format($average, 1)); ?>/5 from <?php echo count($reviews); ?> review(s).</p></div><?php if (!$reviews) : ?><div class="card shadow-sm"><div class="card-body text-center p-5"><p class="text-muted mb-0">No reviews have been submitted yet.</p></div></div><?php else : ?><div class="vstack gap-3"><?php foreach ($reviews as $review) : ?><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between gap-2 mb-2"><strong><?php echo e($review['reviewer_name']); ?></strong><span class="text-brand"><?php echo str_repeat('*', (int) $review['rating']); ?> <?php echo e($review['rating']); ?>/5</span></div><p class="mb-1"><?php echo nl2br(e($review['review_text'])); ?></p><span class="small text-muted"><?php echo e(date('d M Y', strtotime($review['created_at']))); ?></span></div></div><?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
