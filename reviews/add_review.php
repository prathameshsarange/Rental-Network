<?php
require_once __DIR__ . '/../includes/db.php';
require_login();
$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$statement = $pdo->prepare("SELECT b.booking_id, b.item_id, b.renter_id, b.owner_id, b.booking_status, i.item_name FROM bookings b INNER JOIN items i ON i.item_id = b.item_id WHERE b.booking_id = ? AND b.renter_id = ? LIMIT 1");
$statement->execute([$bookingId, current_user_id()]);
$booking = $statement->fetch();
if (!$booking || $booking['booking_status'] !== 'Completed') { set_flash('danger', 'Only completed rentals can be reviewed.'); redirect('booking/my_bookings.php'); }
$existing = $pdo->prepare('SELECT review_id FROM reviews WHERE booking_id = ?'); $existing->execute([$bookingId]);
if ($existing->fetch()) { set_flash('warning', 'You have already reviewed this rental.'); redirect('booking/booking_details.php?id=' . $bookingId); }
$page_title = 'Review Rental'; $rating = (int) ($_POST['rating'] ?? 5); $reviewText = trim($_POST['review_text'] ?? ''); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rating < 1 || $rating > 5) { $errors[] = 'Choose a rating from 1 to 5.'; }
    if ($reviewText === '' || strlen($reviewText) > 1000) { $errors[] = 'Write a review of up to 1000 characters.'; }
    if (!$errors) {
        try {
            $insert = $pdo->prepare('INSERT INTO reviews (booking_id, item_id, reviewer_id, owner_id, rating, review_text) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([$bookingId, $booking['item_id'], current_user_id(), $booking['owner_id'], $rating, $reviewText]);
            $reviewId = (int) $pdo->lastInsertId();
            create_notification($pdo, (int) $booking['owner_id'], 'New rating received for ' . $booking['item_name'] . '.', 'New Rating', $reviewId);
            set_flash('success', 'Thank you for your review.'); redirect('reviews/reviews.php?item_id=' . $booking['item_id']);
        } catch (PDOException $exception) { $errors[] = 'This rental has already been reviewed.'; }
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-7"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4 mb-2">Rate <?php echo e($booking['item_name']); ?></h1><p class="text-muted">Share your experience with this completed rental.</p><?php if ($errors) : ?><div class="alert alert-danger"><?php foreach ($errors as $error) : ?><div><?php echo e($error); ?></div><?php endforeach; ?></div><?php endif; ?><form method="post"><input type="hidden" name="booking_id" value="<?php echo e($bookingId); ?>"><div class="mb-3"><label class="form-label required" for="rating">Rating</label><select class="form-select" id="rating" name="rating"><?php for ($value = 5; $value >= 1; $value--) : ?><option value="<?php echo $value; ?>" <?php echo $rating === $value ? 'selected' : ''; ?>><?php echo str_repeat('*', $value); ?> (<?php echo $value; ?>/5)</option><?php endfor; ?></select></div><div class="mb-4"><label class="form-label required" for="review_text">Review</label><textarea class="form-control" id="review_text" name="review_text" rows="5" maxlength="1000" required><?php echo e($reviewText); ?></textarea></div><button class="btn btn-brand" type="submit">Submit Review</button> <a class="btn btn-outline-secondary" href="<?php echo e(base_url('booking/booking_details.php?id=' . $bookingId)); ?>">Cancel</a></form></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
