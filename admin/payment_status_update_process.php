<?php
/**
 * admin/payment_status_update_process.php
 *
 * Lets a logged-in admin verify a payment's status:
 *   pending -> paid | failed   (normal verification)
 *   paid    -> refunded        (e.g. booking later cancelled)
 * No other transition is allowed - a status is only ever moved
 * forward, never silently reverted, so the record stays a real audit
 * trail of what happened.
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../payments_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_json(false, 'Invalid request method.');
}
if (!admin_is_admin_role()) {
    admin_json(false, "You don't have permission to do that.");
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_json(false, 'Your session expired. Please refresh the page and try again.');
}

$id = (int)($_POST['id'] ?? 0);
$new_status = admin_clean($_POST['status'] ?? '');

if (!in_array($new_status, ['paid', 'failed', 'refunded'], true)) {
    admin_json(false, 'Invalid payment status.');
}

$stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    admin_json(false, 'Payment record not found.');
}

if (($new_status === 'paid' || $new_status === 'failed') && !payment_can_verify($payment)) {
    admin_json(false, 'Only a pending payment can be marked ' . $new_status . '.');
}
if ($new_status === 'refunded' && !payment_can_refund($payment)) {
    admin_json(false, 'Only a paid payment can be refunded.');
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
// payment_date records when it was actually confirmed PAID - a
// refund doesn't change that historical fact, only 'paid' does.
$payment_date = $new_status === 'paid' ? date('Y-m-d H:i:s') : $payment['payment_date'];

$stmt = $conn->prepare(
    "UPDATE payments SET status = ?, payment_date = ?, verified_by = ?, verified_at = NOW() WHERE id = ?"
);
$stmt->bind_param('ssii', $new_status, $payment_date, $admin_id, $id);
$stmt->execute();
$stmt->close();

admin_json(true, 'Payment marked as ' . ucfirst($new_status) . '.');
