<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();
admin_require_admin_role();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_flash_set('error', 'Invalid request method.');
    header('Location: users.php');
    exit;
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_flash_set('error', 'Your session expired. Please try again.');
    header('Location: users.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

// Can't disable your own account - that would be an instant self-lockout.
if ($id <= 0 || $id === (int)$_SESSION['admin_id']) {
    admin_flash_set('error', 'Invalid account.');
    header('Location: users.php');
    exit;
}

$stmt = $conn->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    admin_flash_set('error', 'Account not found.');
    header('Location: users.php');
    exit;
}

$new_status = (int)$user['status'] === 1 ? 0 : 1;

$stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
$stmt->bind_param('ii', $new_status, $id);
$stmt->execute();
$stmt->close();

admin_flash_set('success', $new_status === 1 ? 'Account re-enabled.' : 'Account disabled.');
header('Location: users.php');
exit;
