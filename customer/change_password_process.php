<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';

if (!customer_is_logged_in()) {
    customer_json(false, 'Please log in again.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$customer_id       = (int)$_SESSION['customer_id'];
$current_password  = (string)($_POST['current_password'] ?? '');
$new_password      = (string)($_POST['new_password'] ?? '');
$confirm_password  = (string)($_POST['confirm_password'] ?? '');

if (strlen($new_password) < 8) {
    customer_json(false, 'New password must be at least 8 characters.');
}
if ($new_password !== $confirm_password) {
    customer_json(false, 'New passwords do not match.');
}

$stmt = $conn->prepare('SELECT password FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($current_password, $row['password'])) {
    customer_json(false, 'Your current password is incorrect.');
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE customers SET password = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $customer_id);
if (!$stmt->execute()) {
    $stmt->close();
    customer_json(false, 'Could not update your password. Please try again.');
}
$stmt->close();

customer_json(true, 'Your password has been changed.');
