<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../bookings_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_json(false, 'Invalid request method.');
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_json(false, 'Your session expired. Please refresh the page and try again.');
}

$id = (int)($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM checked WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    admin_json(false, 'Booking not found.');
}
if (!booking_can_check_in($booking)) {
    admin_json(false, 'This booking cannot be checked in right now.');
}

$stmt = $conn->prepare("UPDATE checked SET status = 1 WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

admin_json(true, 'Guest checked in.');
