<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$itemId = (int) ($_GET['item_id'] ?? 0);
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$rentalUnit = $_GET['rental_unit'] ?? 'Daily';
$startTime = $_GET['start_time'] ?? '';
$endTime = $_GET['end_time'] ?? '';
$start = DateTime::createFromFormat('Y-m-d', $startDate);
$end = DateTime::createFromFormat('Y-m-d', $endDate);

if ($itemId < 1 || !$start || $start->format('Y-m-d') !== $startDate || !$end || $end->format('Y-m-d') !== $endDate || $end < $start) {
    http_response_code(422);
    echo json_encode(['available' => false, 'message' => 'Choose valid start and end dates.']);
    exit;
}

if ($rentalUnit === 'Hourly' && ($startDate !== $endDate || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $startTime) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $endTime) || $endTime <= $startTime)) {
    http_response_code(422);
    echo json_encode(['available' => false, 'message' => 'Choose a valid hourly time slot on one day.']);
    exit;
}

$itemStatement = $pdo->prepare("SELECT item_id FROM items WHERE item_id = ? AND status = 'Available' AND is_approved = 1 LIMIT 1");
$itemStatement->execute([$itemId]);
if (!$itemStatement->fetch()) {
    echo json_encode(['available' => false, 'message' => 'This item is not available for booking.']);
    exit;
}

$available = $rentalUnit === 'Hourly'
    ? !has_hourly_booking_conflict($pdo, $itemId, $startDate, $startTime, $endTime)
    : !has_booking_conflict($pdo, $itemId, $startDate, $endDate);
echo json_encode([
    'available' => $available,
    'message' => $available ? 'This rental period is currently available.' : 'This rental period overlaps with an existing rental request.',
]);
