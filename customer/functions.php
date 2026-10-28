<?php
/**
 * customer/functions.php
 *
 * Shared helpers for the customer authentication system. Included by
 * both the page templates (login.php, register.php, account.php, ...)
 * and the standalone AJAX processors in this folder.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** True if a customer is currently logged in. */
function customer_is_logged_in() {
    return isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
}

/**
 * Guard for pages that require a logged-in customer.
 * Must run before any HTML output — index.php wraps its output in
 * ob_start() so this header() redirect works even though the file
 * that includes this page has already printed some markup.
 */
function customer_require_login($redirect_to = 'index.php?page=login') {
    if (!customer_is_logged_in()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/** Trim + strip tags for values that will be stored in the database. */
function customer_clean($value) {
    return trim(strip_tags((string)($value ?? '')));
}

/** Escape a value for safe HTML output. */
function customer_esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function customer_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** One CSRF token per session, used on every state-changing customer form. */
function customer_csrf_token() {
    if (empty($_SESSION['customer_csrf'])) {
        $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['customer_csrf'];
}

function customer_csrf_check($token) {
    return !empty($_SESSION['customer_csrf']) && hash_equals($_SESSION['customer_csrf'], (string)$token);
}

/** Standard JSON response used by every AJAX processor in this folder. */
function customer_json($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => (bool)$success, 'message' => $message], $extra));
    exit;
}
