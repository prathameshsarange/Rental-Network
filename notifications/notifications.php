<?php
require_once __DIR__ . '/../includes/db.php';
require_login();

$userId = current_user_id();
if (isset($_GET['read'])) {
    $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
    $statement->execute([(int) $_GET['read'], $userId]);
    if (isset($_GET['item_id']) && ctype_digit((string) $_GET['item_id']) && (int) $_GET['item_id'] > 0) {
        redirect('items/item_details.php?id=' . (int) $_GET['item_id']);
    }
    if (isset($_GET['booking_id']) && ctype_digit((string) $_GET['booking_id']) && (int) $_GET['booking_id'] > 0) {
        redirect('booking/booking_details.php?id=' . (int) $_GET['booking_id']);
    }
    if (isset($_GET['extension_id']) && ctype_digit((string) $_GET['extension_id']) && (int) $_GET['extension_id'] > 0) {
        $extensionStatement = $pdo->prepare('SELECT booking_id FROM booking_extensions WHERE extension_id = ? LIMIT 1');
        $extensionStatement->execute([(int) $_GET['extension_id']]);
        $extension = $extensionStatement->fetch();
        if ($extension) {
            redirect('booking/booking_details.php?id=' . (int) $extension['booking_id']);
        }
    }
    if (isset($_GET['return_id']) && ctype_digit((string) $_GET['return_id']) && (int) $_GET['return_id'] > 0) {
        $returnStatement = $pdo->prepare('SELECT booking_id FROM `returns` WHERE return_id = ? LIMIT 1');
        $returnStatement->execute([(int) $_GET['return_id']]);
        $return = $returnStatement->fetch();
        if ($return) {
            redirect('booking/booking_details.php?id=' . (int) $return['booking_id']);
        }
    }
    if (isset($_GET['review_id']) && ctype_digit((string) $_GET['review_id']) && (int) $_GET['review_id'] > 0) {
        $reviewStatement = $pdo->prepare('SELECT booking_id FROM reviews WHERE review_id = ? LIMIT 1');
        $reviewStatement->execute([(int) $_GET['review_id']]);
        $review = $reviewStatement->fetch();
        if ($review) {
            redirect('booking/booking_details.php?id=' . (int) $review['booking_id']);
        }
    }
    redirect('notifications/notifications.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'read_all') {
    $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $statement->execute([$userId]);
    set_flash('success', 'All notifications marked as read.');
    redirect('notifications/notifications.php');
}

$page_title = 'Notifications';
$statement = $pdo->prepare('SELECT notification_id, message, notification_type, related_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC');
$statement->execute([$userId]);
$notifications = $statement->fetchAll();
$unreadCount = count(array_filter($notifications, static fn(array $notification): bool => !(bool) $notification['is_read']));
$bookingTypes = ['New Rental Request', 'Rental Accepted', 'Rental Rejected', 'Booking Cancelled', 'Payment Successful', 'Return Reminder', 'Return Due Today', 'Rental Overdue'];
$extensionTypes = ['Extension Request', 'Extension Accepted', 'Extension Rejected', 'Extension Payment Successful'];
$returnTypes = ['Return Request', 'Return Accepted', 'Return Disputed'];
$notificationStyle = static function (string $type): array {
    if ($type === 'Nearby Item') return ['nearby', 'NEAR'];
    if (str_contains($type, 'Payment')) return ['payment', 'PAY'];
    if (str_contains($type, 'Return') || str_contains($type, 'Overdue')) return ['return', 'RETURN'];
    if (str_contains($type, 'Extension')) return ['extension', 'EXTEND'];
    if (str_contains($type, 'Rental') || str_contains($type, 'Booking')) return ['rental', 'RENT'];
    if (str_contains($type, 'Rating')) return ['rating', 'RATE'];
    return ['general', 'UPDATE'];
};
require_once __DIR__ . '/../includes/header.php';
?>
<div class="notification-page max-w-1400">
    <div class="notification-hero d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-4">
        <div><span class="notification-eyebrow">Activity centre</span><h1 class="h3 mb-1">Notifications</h1><p class="mb-0">Rental updates, nearby listings, payments, and returns in one place.</p></div>
        <div class="d-flex align-items-center gap-3"><div class="notification-count"><strong><?php echo e((string) $unreadCount); ?></strong><span>unread</span></div><?php if ($notifications && $unreadCount > 0) : ?><form method="post"><input type="hidden" name="action" value="read_all"><button class="btn btn-outline-brand" type="submit">Mark All Read</button></form><?php endif; ?></div>
    </div>
    <?php if (!$notifications) : ?>
        <div class="notification-empty"><div class="notification-symbol notification-general">UPDATE</div><h2 class="h4">No notifications yet</h2><p class="text-muted mb-0">New activity on your rentals will appear here.</p></div>
    <?php else : ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification) : ?>
                <?php
                $target = 'notifications/notifications.php?read=' . $notification['notification_id'];
                if ($notification['notification_type'] === 'Nearby Item' && !empty($notification['related_id'])) { $target .= '&item_id=' . (int) $notification['related_id']; }
                elseif (in_array($notification['notification_type'], $extensionTypes, true) && !empty($notification['related_id'])) { $target .= '&extension_id=' . (int) $notification['related_id']; }
                elseif (in_array($notification['notification_type'], $returnTypes, true) && !empty($notification['related_id'])) { $target .= '&return_id=' . (int) $notification['related_id']; }
                elseif ($notification['notification_type'] === 'New Rating' && !empty($notification['related_id'])) { $target .= '&review_id=' . (int) $notification['related_id']; }
                elseif (in_array($notification['notification_type'], $bookingTypes, true) && !empty($notification['related_id'])) { $target .= '&booking_id=' . (int) $notification['related_id']; }
                [$style, $label] = $notificationStyle($notification['notification_type']);
                ?>
                <a class="notification-card notification-<?php echo e($style); ?> <?php echo !$notification['is_read'] ? 'notification-unread' : ''; ?>" href="<?php echo e(base_url($target)); ?>">
                    <span class="notification-symbol notification-<?php echo e($style); ?>"><?php echo e($label); ?></span>
                    <span class="notification-content"><span class="notification-type"><?php echo e($notification['notification_type']); ?></span><span class="notification-message"><?php echo e($notification['message']); ?></span><span class="notification-time"><?php echo e(date('d M Y, h:i A', strtotime($notification['created_at']))); ?></span></span>
                    <span class="notification-side"><?php if (!$notification['is_read']) : ?><span class="notification-new">New</span><?php endif; ?><span class="notification-arrow" aria-hidden="true">&rarr;</span></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
