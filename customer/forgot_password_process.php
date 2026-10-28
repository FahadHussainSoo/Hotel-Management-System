<?php
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$email = strtolower(trim($_POST['email'] ?? ''));
$generic_message = 'If an account with that email exists, a reset link has been sent.';

if (!customer_valid_email($email)) {
    // Same generic message either way — don't leak whether the email exists.
    customer_json(true, $generic_message);
}

$stmt = $conn->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($customer) {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare('UPDATE customers SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
    $stmt->bind_param('ssi', $token, $expires, $customer['id']);
    $stmt->execute();
    $stmt->close();

    // Build an absolute link back to reset-password.php regardless of
    // which subfolder the app is installed under (e.g. localhost/Hotel-...).
    $base   = rtrim(str_replace('/customer/forgot_password_process.php', '', $_SERVER['SCRIPT_NAME']), '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $reset_link = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base
        . '/index.php?page=reset-password&email=' . urlencode($email) . '&token=' . $token;

    send_customer_email(
        $email,
        'Reset your password',
        '<p>We received a request to reset your password.</p>'
        . '<p><a href="' . htmlspecialchars($reset_link) . '">Click here to reset your password</a></p>'
        . '<p>This link expires in 1 hour. If you did not request this, you can safely ignore this email.</p>'
    );

    // Always logged locally (server-side only) so the flow is testable
    // on XAMPP before real SMTP is configured — never sent to the browser.
    log_password_reset_link($email, $reset_link);
}

customer_json(true, $generic_message);
