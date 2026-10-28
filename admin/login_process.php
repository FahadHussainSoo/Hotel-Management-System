<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_json(false, 'Invalid request method.');
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_json(false, 'Your session expired. Please refresh the page and try again.');
}

$username = admin_clean($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    admin_json(false, 'Please enter your username and password.');
}

$stmt = $conn->prepare('SELECT id, name, username, password, type, status FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$invalid_msg = 'Incorrect username or password.';

if (!$user) {
    admin_json(false, $invalid_msg);
}

if ((int)$user['status'] === 0) {
    admin_json(false, 'This account has been disabled. Contact an administrator.');
}

// The seed `users` row ships with a plain-text password. Accept a
// properly hashed password first; fall back to a legacy plain-text
// match and transparently upgrade it to a hash on success.
$password_ok = password_verify($password, $user['password']);
if (!$password_ok && hash_equals($user['password'], $password)) {
    $password_ok = true;
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->bind_param('si', $new_hash, $user['id']);
    $upd->execute();
    $upd->close();
}

if (!$password_ok) {
    admin_json(false, $invalid_msg);
}

session_regenerate_id(true);
$_SESSION['admin_id']       = (int)$user['id'];
$_SESSION['admin_name']     = $user['name'];
$_SESSION['admin_username'] = $user['username'];
$_SESSION['admin_type']     = (int)$user['type'];

admin_json(true, 'Logged in successfully.', ['redirect' => 'index.php']);
