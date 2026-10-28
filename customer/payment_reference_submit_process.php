<?php
/**
 * customer/payment_reference_submit_process.php
 *
 * Lets a logged-in customer attach/update a bank transfer reference
 * number on their OWN payment record, only while it's still pending
 * (once the admin verifies it, this is locked to keep the audit
 * trail honest).
 */
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../payments_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    customer_json(false, 'Invalid request method.');
}
if (!customer_is_logged_in()) {
    customer_json(false, 'Please log in.', ['require_login' => true]);
}
if (!customer_csrf_check($_POST['csrf_token'] ?? '')) {
    customer_json(false, 'Your session expired. Please refresh the page and try again.');
}

$payment_id = (int)($_POST['payment_id'] ?? 0);
$reference_no = customer_clean($_POST['reference_no'] ?? '');

if ($reference_no === '') {
    customer_json(false, 'Please enter your bank transfer reference number.');
}
if (mb_strlen($reference_no) > 100) {
    $reference_no = mb_substr($reference_no, 0, 100);
}

// Ownership check: the payment must belong to a booking that belongs
// to THIS logged-in customer - never trust payment_id alone.
$stmt = $conn->prepare(
    "SELECT p.* FROM payments p
     INNER JOIN checked c ON c.id = p.booking_id
     WHERE p.id = ? AND c.booked_cid = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $payment_id, $_SESSION['customer_id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    customer_json(false, 'Payment record not found.');
}
if (!payment_can_verify($payment)) {
    customer_json(false, 'This payment has already been processed and can no longer be edited.');
}

$stmt = $conn->prepare("UPDATE payments SET reference_no = ? WHERE id = ?");
$stmt->bind_param('si', $reference_no, $payment_id);
$stmt->execute();
$stmt->close();

customer_json(true, 'Reference number saved. The hotel will verify your payment shortly.');
