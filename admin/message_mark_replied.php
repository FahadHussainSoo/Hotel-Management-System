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
    admin_json(false, 'Invalid message.');
}

$stmt = $conn->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

admin_json(true, 'Marked as replied.');
