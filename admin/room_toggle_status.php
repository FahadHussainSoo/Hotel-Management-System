<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_json(false, 'Invalid request method.');
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_json(false, 'Your session expired. Please refresh the page and try again.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    admin_json(false, 'Invalid room.');
}

$stmt = $conn->prepare("SELECT status FROM rooms WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    admin_json(false, 'Room not found.');
}

$new_status = (int)$room['status'] === 0 ? 1 : 0;

$stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
$stmt->bind_param('ii', $new_status, $id);
$stmt->execute();
$stmt->close();

admin_json(true, $new_status === 0 ? 'Room enabled.' : 'Room disabled.', ['status' => $new_status]);
