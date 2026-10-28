<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$email    = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if (!customer_valid_email($email) || $password === '') {
    customer_json(false, 'Please enter a valid email and password.');
}

$stmt = $conn->prepare('SELECT id, full_name, email, password, status FROM customers WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Deliberately generic — never reveal whether the email exists.
$invalid_msg = 'Incorrect email or password.';

if (!$customer || !password_verify($password, $customer['password'])) {
    customer_json(false, $invalid_msg);
}
if ((int)$customer['status'] !== 1) {
    customer_json(false, 'This account has been disabled. Please contact the hotel.');
}

session_regenerate_id(true);
$_SESSION['customer_id']    = (int)$customer['id'];
$_SESSION['customer_name']  = $customer['full_name'];
$_SESSION['customer_email'] = $customer['email'];

customer_json(true, 'Logged in successfully.', ['redirect' => 'index.php?page=account']);
