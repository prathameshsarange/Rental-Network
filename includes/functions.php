<?php
require_once __DIR__ . '/location_config.php';

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/RentalNetwork/');
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

function base_url(string $path = ''): string
{
    if ($path !== '' && preg_match('/^https?:\/\//', $path)) {
        return $path;
    }

    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function display_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    foreach ($_SESSION['flash'] as $flash) {
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">';
        echo e($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }

    unset($_SESSION['flash']);
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please login to continue.');
        redirect('login.php');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect('dashboard.php');
    }
}

function require_admin(): void
{
    require_login();

    if (current_user_role() !== 'Admin') {
        set_flash('danger', 'You are not allowed to access the admin area.');
        redirect('dashboard.php');
    }
}

function current_user(PDO $pdo): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
    $statement->execute([current_user_id()]);
    $user = $statement->fetch();

    return $user ?: null;
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

function create_notification(PDO $pdo, int $userId, string $message, string $type, ?int $relatedId = null): void
{
    $statement = $pdo->prepare('INSERT INTO notifications (user_id, message, notification_type, related_id) VALUES (?, ?, ?, ?)');
    $statement->execute([$userId, $message, $type, $relatedId]);
}

function valid_latitude(float|string|null $latitude): bool
{
    return is_numeric($latitude) && (float) $latitude >= -90 && (float) $latitude <= 90;
}

function valid_longitude(float|string|null $longitude): bool
{
    return is_numeric($longitude) && (float) $longitude >= -180 && (float) $longitude <= 180;
}

function valid_coordinates(float|string|null $latitude, float|string|null $longitude): bool
{
    return valid_latitude($latitude) && valid_longitude($longitude);
}

function haversine_distance_km(float $latitudeOne, float $longitudeOne, float $latitudeTwo, float $longitudeTwo): float
{
    $earthRadiusKm = 6371.0;
    $latitudeDelta = deg2rad($latitudeTwo - $latitudeOne);
    $longitudeDelta = deg2rad($longitudeTwo - $longitudeOne);
    $a = sin($latitudeDelta / 2) ** 2
        + cos(deg2rad($latitudeOne)) * cos(deg2rad($latitudeTwo)) * sin($longitudeDelta / 2) ** 2;

    return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function approximate_map_coordinates(float $latitude, float $longitude): array
{
    // Public pages use a rounded pin; exact coordinates remain private in the database.
    return [round($latitude, 2), round($longitude, 2)];
}

function notify_nearby_users(PDO $pdo, int $itemId): void
{
    $itemStatement = $pdo->prepare('SELECT item_id, owner_id, item_name, location_address, city, latitude, longitude FROM items WHERE item_id = ? LIMIT 1');
    $itemStatement->execute([$itemId]);
    $item = $itemStatement->fetch();

    if (!$item || !valid_coordinates($item['latitude'], $item['longitude'])) {
        return;
    }

    $usersStatement = $pdo->prepare("SELECT user_id, latitude, longitude FROM users WHERE user_id <> ? AND status = 'Active' AND latitude IS NOT NULL AND longitude IS NOT NULL");
    $usersStatement->execute([(int) $item['owner_id']]);
    $notificationExists = $pdo->prepare("SELECT notification_id FROM notifications WHERE user_id = ? AND notification_type = 'Nearby Item' AND related_id = ? LIMIT 1");
    $insertNotification = $pdo->prepare('INSERT INTO notifications (user_id, message, notification_type, related_id) VALUES (?, ?, ?, ?)');

    foreach ($usersStatement->fetchAll() as $user) {
        if (!valid_coordinates($user['latitude'], $user['longitude'])) {
            continue;
        }

        $distance = haversine_distance_km((float) $item['latitude'], (float) $item['longitude'], (float) $user['latitude'], (float) $user['longitude']);
        if ($distance > NEARBY_RADIUS_KM) {
            continue;
        }

        $notificationExists->execute([(int) $user['user_id'], $itemId]);
        if ($notificationExists->fetch()) {
            continue;
        }

        $place = trim((string) ($item['location_address'] ?: $item['city']));
        $message = $item['item_name'] . ' is available for rent near ' . $place . '. Distance: ' . number_format($distance, 1) . ' KM.';
        $insertNotification->execute([(int) $user['user_id'], $message, 'Nearby Item', $itemId]);
    }
}

function notify_user_of_nearby_items(PDO $pdo, int $userId): void
{
    $userStatement = $pdo->prepare('SELECT user_id, latitude, longitude FROM users WHERE user_id = ? AND status = \'Active\' LIMIT 1');
    $userStatement->execute([$userId]);
    $user = $userStatement->fetch();

    if (!$user || !valid_coordinates($user['latitude'], $user['longitude'])) {
        return;
    }

    $itemsStatement = $pdo->prepare("SELECT item_id, item_name, location_address, city, latitude, longitude FROM items WHERE owner_id <> ? AND is_approved = 1 AND status = 'Available' AND latitude IS NOT NULL AND longitude IS NOT NULL");
    $itemsStatement->execute([$userId]);
    $notificationExists = $pdo->prepare("SELECT notification_id FROM notifications WHERE user_id = ? AND notification_type = 'Nearby Item' AND related_id = ? LIMIT 1");
    $insertNotification = $pdo->prepare('INSERT INTO notifications (user_id, message, notification_type, related_id) VALUES (?, ?, ?, ?)');

    foreach ($itemsStatement->fetchAll() as $item) {
        if (!valid_coordinates($item['latitude'], $item['longitude'])) {
            continue;
        }

        $distance = haversine_distance_km((float) $user['latitude'], (float) $user['longitude'], (float) $item['latitude'], (float) $item['longitude']);
        if ($distance > NEARBY_RADIUS_KM) {
            continue;
        }

        $notificationExists->execute([$userId, (int) $item['item_id']]);
        if ($notificationExists->fetch()) {
            continue;
        }

        $place = trim((string) ($item['location_address'] ?: $item['city']));
        $message = $item['item_name'] . ' is available for rent near ' . $place . '. Distance: ' . number_format($distance, 1) . ' KM.';
        $insertNotification->execute([$userId, $message, 'Nearby Item', (int) $item['item_id']]);
    }
}

function create_unique_notification(PDO $pdo, int $userId, string $message, string $type, int $relatedId): bool
{
    $exists = $pdo->prepare('SELECT notification_id FROM notifications WHERE user_id = ? AND notification_type = ? AND related_id = ? LIMIT 1');
    $exists->execute([$userId, $type, $relatedId]);
    if ($exists->fetch()) {
        return false;
    }

    try {
        create_notification($pdo, $userId, $message, $type, $relatedId);
        return true;
    } catch (PDOException $exception) {
        // The database unique index protects against duplicate scheduler runs.
        if ($exception->getCode() === '23000') {
            return false;
        }
        throw $exception;
    }
}

function send_rental_due_reminders(PDO $pdo): int
{
    $today = new DateTimeImmutable('today');
    $tomorrow = $today->modify('+1 day');
    $statement = $pdo->prepare("SELECT b.booking_id, b.renter_id, b.end_date, i.item_name
        FROM bookings b
        INNER JOIN items i ON i.item_id = b.item_id
        WHERE b.booking_status IN ('Confirmed', 'Active') AND b.end_date <= ?");
    $statement->execute([$tomorrow->format('Y-m-d')]);
    $lateFeePerDay = max(0, (float) setting_value($pdo, 'late_fee_per_day', '100'));
    $sentCount = 0;

    foreach ($statement->fetchAll() as $booking) {
        $endDate = new DateTimeImmutable($booking['end_date']);
        $type = '';
        $message = '';

        if ($endDate->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
            $type = 'Return Reminder';
            $message = 'Reminder: ' . $booking['item_name'] . ' is due for return tomorrow (' . $endDate->format('d M Y') . ').';
        } elseif ($endDate->format('Y-m-d') === $today->format('Y-m-d')) {
            $type = 'Return Due Today';
            $message = 'Return due today: Please return ' . $booking['item_name'] . ' by the agreed end date.';
        } elseif ($endDate < $today) {
            $lateDays = (int) $endDate->diff($today)->days;
            $lateFee = $lateDays * $lateFeePerDay;
            $type = 'Rental Overdue';
            $message = $booking['item_name'] . ' is overdue by ' . $lateDays . ' day(s). Current late fee: ' . format_money($lateFee) . '.';
        }

        if ($type !== '' && create_unique_notification($pdo, (int) $booking['renter_id'], $message, $type, (int) $booking['booking_id'])) {
            $sentCount++;
        }
    }

    return $sentCount;
}

function setting_value(PDO $pdo, string $key, string $default = ''): string
{
    $statement = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function has_booking_conflict(PDO $pdo, int $itemId, string $startDate, string $endDate, ?int $excludeBookingId = null): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM bookings
        WHERE item_id = ?
          AND booking_status IN ('Pending', 'Accepted', 'Payment Pending', 'Confirmed', 'Active', 'Return Requested', 'Returned')
          AND start_date <= ?
          AND end_date >= ?
    ";
    $params = [$itemId, $endDate, $startDate];

    if ($excludeBookingId !== null) {
        $sql .= ' AND booking_id <> ?';
        $params[] = $excludeBookingId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function has_hourly_booking_conflict(PDO $pdo, int $itemId, string $bookingDate, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
{
    $startAt = $bookingDate . ' ' . $startTime;
    $endAt = $bookingDate . ' ' . $endTime;
    $sql = "
        SELECT COUNT(*)
        FROM bookings
        WHERE item_id = ?
          AND booking_status IN ('Pending', 'Accepted', 'Payment Pending', 'Confirmed', 'Active', 'Return Requested', 'Returned')
          AND (
              (rental_unit = 'Daily' AND start_date <= ? AND end_date >= ?)
              OR
              (rental_unit = 'Hourly'
                  AND CONCAT(start_date, ' ', start_time) < ?
                  AND CONCAT(end_date, ' ', end_time) > ?)
          )
    ";
    $params = [$itemId, $bookingDate, $bookingDate, $endAt, $startAt];

    if ($excludeBookingId !== null) {
        $sql .= ' AND booking_id <> ?';
        $params[] = $excludeBookingId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function password_strength_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }

    return null;
}

function handle_image_upload(array $file, string $subdirectory, string $prefix): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, null, 'Image upload failed. Please try again.'];
    }

    $maxSize = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return [false, null, 'Image must be 2 MB or smaller.'];
    }

    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!array_key_exists($extension, $allowedTypes)) {
        return [false, null, 'Only JPG, JPEG, PNG, and WEBP images are allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if ($mimeType !== $allowedTypes[$extension]) {
        return [false, null, 'The uploaded file is not a valid image.'];
    }

    $targetDirectory = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . trim($subdirectory, '/\\');
    if (!is_dir($targetDirectory)) {
        mkdir($targetDirectory, 0755, true);
    }

    $safeFilename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $safeFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [false, null, 'Could not save the uploaded image.'];
    }

    return [true, $safeFilename, null];
}

function uploaded_image_url(string $subdirectory, ?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }

    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $filename)) {
        return null;
    }

    $cleanDirectory = trim($subdirectory, '/\\');
    $filePath = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $cleanDirectory . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($filePath)) {
        return null;
    }

    return base_url('uploads/' . $cleanDirectory . '/' . rawurlencode($filename));
}

function item_image_url(?string $image): ?string
{
    $image = trim((string) $image);

    if ($image === '') {
        return null;
    }

    if (filter_var($image, FILTER_VALIDATE_URL)) {
        $host = parse_url($image, PHP_URL_HOST);
        return $host === 'images.unsplash.com' && str_starts_with($image, 'https://') ? $image : null;
    }

    return uploaded_image_url('item_images', $image);
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Available', 'Active', 'Approved', 'Completed', 'Paid' => 'success',
        'Pending', 'Payment Pending', 'Return Requested' => 'warning',
        'Unavailable', 'Maintenance', 'Blocked', 'Rejected', 'Cancelled', 'Failed', 'Disputed' => 'danger',
        'Rented', 'Accepted', 'Confirmed', 'Active', 'Returned' => 'info',
        default => 'secondary',
    };
}

function format_money(float|string $amount): string
{
    return 'Rs. ' . number_format((float) $amount, 2);
}

function dashboard_count(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}
