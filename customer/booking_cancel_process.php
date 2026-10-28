<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../bookings_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_is_logged_in()) {
    customer_json(false, 'Please log in to manage your bookings.', ['require_login' => true]);
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$booking_id = (int)($_POST['id'] ?? 0);
$reason = customer_clean($_POST['reason'] ?? '');

$stmt = $conn->prepare("SELECT * FROM checked WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    customer_json(false, 'Booking not found.');
}

// Ownership check - a customer may only ever act on their own bookings.
if ((int)$booking['booked_cid'] !== (int)$_SESSION['customer_id']) {
    customer_json(false, 'Booking not found.');
}

if (!booking_can_cancel($booking)) {
    customer_json(false, 'This booking can no longer be cancelled.');
}

$stmt = $conn->prepare("UPDATE checked SET booking_status = 'cancelled', cancel_reason = ? WHERE id = ?");
$stmt->bind_param('si', $reason, $booking_id);
$stmt->execute();
$stmt->close();

customer_json(true, 'Your booking has been cancelled.');
