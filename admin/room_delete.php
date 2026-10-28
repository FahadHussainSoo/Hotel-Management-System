<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: rooms.php');
    exit;
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_flash_set('error', 'Your session expired. Please try again.');
    header('Location: rooms.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    admin_flash_set('error', 'Invalid room.');
    header('Location: rooms.php');
    exit;
}

// Safety check: don't delete a room that has reservation history —
// that would silently orphan rows in `checked`.
$stmt = $conn->prepare("SELECT COUNT(*) c FROM checked WHERE room_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$booking_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

if ($booking_count > 0) {
    admin_flash_set('error', "Can't delete this room — it has $booking_count reservation record(s) linked to it. Disable it instead.");
    header('Location: rooms.php');
    exit;
}

$stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

admin_flash_set('success', 'Room deleted.');
header('Location: rooms.php');
exit;
