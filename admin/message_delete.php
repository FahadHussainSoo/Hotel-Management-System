<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messages.php');
    exit;
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_flash_set('error', 'Your session expired. Please try again.');
    header('Location: messages.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    admin_flash_set('error', 'Invalid message.');
    header('Location: messages.php');
    exit;
}

$stmt = $conn->prepare('DELETE FROM contact_messages WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

admin_flash_set('success', 'Message deleted.');
header('Location: messages.php');
exit;
