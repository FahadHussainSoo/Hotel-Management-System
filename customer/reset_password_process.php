<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$email             = strtolower(trim($_POST['email'] ?? ''));
$token             = trim($_POST['token'] ?? '');
$new_password      = (string)($_POST['new_password'] ?? '');
$confirm_password  = (string)($_POST['confirm_password'] ?? '');

if (strlen($new_password) < 8) {
    customer_json(false, 'Password must be at least 8 characters.');
}
if ($new_password !== $confirm_password) {
    customer_json(false, 'Passwords do not match.');
}

$stmt = $conn->prepare('SELECT id FROM customers WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW() LIMIT 1');
$stmt->bind_param('ss', $email, $token);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    customer_json(false, 'This reset link is invalid or has expired. Please request a new one.');
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE customers SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
$stmt->bind_param('si', $hash, $customer['id']);
$stmt->execute();
$stmt->close();

customer_json(true, 'Your password has been reset. You can now log in.');
