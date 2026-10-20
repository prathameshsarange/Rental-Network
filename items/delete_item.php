<?php
require_once __DIR__ . '/../includes/db.php';

require_login();

$itemId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($itemId < 1) {
    set_flash('danger', 'Invalid item selected.');
    redirect('items/my_items.php');
}

$itemStatement = $pdo->prepare("
    SELECT i.*, c.category_name
    FROM items i
    INNER JOIN categories c ON c.category_id = i.category_id
    WHERE i.item_id = ? AND i.owner_id = ?
    LIMIT 1
");
$itemStatement->execute([$itemId, current_user_id()]);
$item = $itemStatement->fetch();

if (!$item) {
    set_flash('danger', 'Item not found or you are not allowed to delete it.');
    redirect('items/my_items.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingCount = dashboard_count($pdo, 'SELECT COUNT(*) FROM bookings WHERE item_id = ?', [$itemId]);

    try {
        if ($bookingCount > 0) {
            $statement = $pdo->prepare("UPDATE items SET status = 'Unavailable', is_approved = 0 WHERE item_id = ? AND owner_id = ?");
            $statement->execute([$itemId, current_user_id()]);
            set_flash('warning', 'This item has booking history, so it was archived and removed from your active listings.');
        } else {
            $statement = $pdo->prepare('DELETE FROM items WHERE item_id = ? AND owner_id = ?');
            $statement->execute([$itemId, current_user_id()]);
            set_flash('success', 'Item deleted successfully.');
        }

        redirect('items/my_items.php');
    } catch (Throwable $exception) {
        error_log('Delete item error: ' . $exception->getMessage());
        set_flash('danger', 'Item could not be deleted. Please try again.');
        redirect('items/my_items.php');
    }
}

$page_title = 'Delete Item';
$imageUrl = item_image_url($item['image']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card-narrow shadow-sm">
    <div class="card-body p-4">
        <h1 class="h3 mb-3">Delete Item</h1>

        <?php if ($imageUrl !== null) : ?>
            <img src="<?php echo e($imageUrl); ?>" class="img-fluid rounded mb-3" alt="<?php echo e($item['item_name']); ?>">
        <?php else : ?>
            <div class="item-placeholder rounded mb-3"><?php echo e($item['category_name']); ?></div>
        <?php endif; ?>

        <h2 class="h5"><?php echo e($item['item_name']); ?></h2>
        <p class="text-muted"><?php echo e($item['category_name']); ?> | <?php echo e($item['location']); ?></p>

        <div class="alert alert-warning">
            Are you sure you want to delete this item? If it has booking history, it will be hidden from public listings instead.
        </div>

        <form method="post">
            <input type="hidden" name="id" value="<?php echo e($item['item_id']); ?>">
            <div class="d-flex flex-column flex-md-row gap-2">
                <button type="submit" class="btn btn-danger">Delete Item</button>
                <a href="<?php echo e(base_url('items/my_items.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
