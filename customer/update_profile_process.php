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

$customer_id = (int)$_SESSION['customer_id'];
$full_name   = customer_clean($_POST['full_name'] ?? '');
$email       = strtolower(trim($_POST['email'] ?? ''));
$phone       = customer_clean($_POST['phone'] ?? '');

if ($full_name === '' || strlen($full_name) < 2) {
    customer_json(false, 'Please enter your full name.');
}
if (!customer_valid_email($email)) {
    customer_json(false, 'Please enter a valid email address.');
}
if ($phone === '' || !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
    customer_json(false, 'Please enter a valid phone number.');
}

// Duplicate email protection, excluding this customer's own row.
$stmt = $conn->prepare('SELECT id FROM customers WHERE email = ? AND id != ? LIMIT 1');
$stmt->bind_param('si', $email, $customer_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    customer_json(false, 'That email is already used by another account.');
}
$stmt->close();

$stmt = $conn->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ? WHERE id = ?');
$stmt->bind_param('sssi', $full_name, $email, $phone, $customer_id);
if (!$stmt->execute()) {
    $stmt->close();
    customer_json(false, 'Could not update your profile. Please try again.');
}
$stmt->close();

$_SESSION['customer_name']  = $full_name;
$_SESSION['customer_email'] = $email;

customer_json(true, 'Your profile has been updated.');
