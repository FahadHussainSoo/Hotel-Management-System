<?php
/**
 * customer/logout.php
 *
 * Destroys the customer's session data and returns them to the login
 * page. Linked directly (href="customer/logout.php"), not routed
 * through index.php's ?page= system, so it's free to redirect
 * immediately without any output-buffering concerns.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['customer_email'],
    $_SESSION['customer_csrf']
);
session_regenerate_id(true);

header('Location: ../index.php?page=login');
exit;
