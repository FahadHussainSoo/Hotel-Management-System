<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$full_name        = customer_clean($_POST['full_name'] ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$phone            = customer_clean($_POST['phone'] ?? '');
$password         = (string)($_POST['password'] ?? '');
$confirm_password = (string)($_POST['confirm_password'] ?? '');

// ---- Validation ----
if ($full_name === '' || strlen($full_name) < 2) {
    customer_json(false, 'Please enter your full name.');
}
if (!customer_valid_email($email)) {
    customer_json(false, 'Please enter a valid email address.');
}
if ($phone === '' || !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
    customer_json(false, 'Please enter a valid phone number.');
}
if (strlen($password) < 8) {
    customer_json(false, 'Password must be at least 8 characters.');
}
if ($password !== $confirm_password) {
    customer_json(false, 'Passwords do not match.');
}

// ---- Duplicate email protection ----
$stmt = $conn->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    customer_json(false, 'An account with that email already exists.');
}
$stmt->close();

// ---- Create the account ----
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO customers (full_name, email, phone, password) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $full_name, $email, $phone, $hash);

if (!$stmt->execute()) {
    $stmt->close();
    customer_json(false, 'Could not create your account. Please try again.');
}
$new_id = $stmt->insert_id;
$stmt->close();

// ---- Log the new customer in ----
session_regenerate_id(true);
$_SESSION['customer_id']    = $new_id;
$_SESSION['customer_name']  = $full_name;
$_SESSION['customer_email'] = $email;

customer_json(true, 'Account created successfully.', ['redirect' => 'index.php?page=account']);
