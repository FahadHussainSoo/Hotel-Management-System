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
    admin_json(false, 'Invalid customer.');
}

$stmt = $conn->prepare('SELECT status FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    admin_json(false, 'Customer not found.');
}

$new_status = (int)$customer['status'] === 1 ? 0 : 1;

$stmt = $conn->prepare('UPDATE customers SET status = ? WHERE id = ?');
$stmt->bind_param('ii', $new_status, $id);
$stmt->execute();
$stmt->close();

admin_json(true, $new_status === 1 ? 'Customer account enabled.' : 'Customer account disabled.', ['status' => $new_status]);
