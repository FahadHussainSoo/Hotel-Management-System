<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../payments_functions.php';
admin_require_login();
admin_require_admin_role();

$errors = [];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$amount = '';
$method = 'cash';
$reference_no = '';
$note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $amount = (string)($_POST['amount'] ?? '');
    $method = admin_clean($_POST['method'] ?? 'cash');
    $reference_no = admin_clean($_POST['reference_no'] ?? '');
    $note = admin_clean($_POST['note'] ?? '');

    $amount_f = is_numeric($amount) ? (float)$amount : 0;

    if ($booking_id <= 0) $errors[] = 'Please choose a booking.';
    if ($amount_f <= 0) $errors[] = 'Please enter a valid payment amount.';
    if (!in_array($method, ['cash', 'card', 'bank transfer', 'online', 'cash_on_arrival', 'bank_transfer'], true)) $method = 'cash';
    if (mb_strlen($reference_no) > 100) $reference_no = mb_substr($reference_no, 0, 100);

    if (empty($errors)) {
        $check = $conn->prepare('SELECT id FROM checked WHERE id = ? LIMIT 1');
        $check->bind_param('i', $booking_id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $errors[] = 'That booking no longer exists.';
        }
        $check->close();
    }

    if (empty($errors)) {
        // A payment recorded manually here represents money the front
        // desk has already collected (e.g. cash handed over in
        // person), so it's entered directly as 'paid' - unlike the
        // automatic record created at booking time, which starts
        // 'pending' until the admin verifies it. See
        // payment_status_update_process.php for that verification flow.
        $admin_id = (int)($_SESSION['admin_id'] ?? 0);
        $ref = $reference_no !== '' ? $reference_no : null;
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO payments (booking_id, amount, method, status, reference_no, payment_date, gateway, note, received_by, verified_by, verified_at)
             VALUES (?, ?, ?, 'paid', ?, ?, 'manual', ?, ?, ?, ?)"
        );
        $stmt->bind_param('idssssiis', $booking_id, $amount_f, $method, $ref, $now, $note, $admin_id, $admin_id, $now);
        $stmt->execute();
        $stmt->close();
        admin_flash_set('success', 'Payment recorded.');
        header('Location: payments.php');
        exit;
    }
}

$bookings = $conn->query(
    "SELECT c.id, c.ref_no, c.name, c.total_price,
            (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.booking_id = c.id) AS paid
     FROM checked c
     WHERE c.booking_status = 'approved'
     ORDER BY c.date_updated DESC"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'Record Payment';
include __DIR__ . '/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo admin_esc($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (empty($bookings)): ?>
<div class="alert alert-warning">There are no confirmed bookings to record a payment against yet.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
            <div class="form-group">
                <label>Booking</label>
                <select name="booking_id" class="form-control" required>
                    <option value="">-- Select a confirmed booking --</option>
                    <?php foreach ($bookings as $b):
                        $outstanding = max(0, (float)$b['total_price'] - (float)$b['paid']);
                    ?>
                    <option value="<?php echo (int)$b['id'] ?>" <?php echo $booking_id === (int)$b['id'] ? 'selected' : '' ?>>
                        <?php echo admin_esc($b['ref_no']) ?> &mdash; <?php echo admin_esc($b['name']) ?> (owes $<?php echo number_format($outstanding, 2) ?> of $<?php echo number_format((float)$b['total_price'], 2) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Amount ($)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo admin_esc($amount) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Method</label>
                    <select name="method" class="form-control">
                        <option value="cash" <?php echo $method === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="cash_on_arrival" <?php echo $method === 'cash_on_arrival' ? 'selected' : '' ?>>Cash on Arrival</option>
                        <option value="bank_transfer" <?php echo $method === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="card" <?php echo $method === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="online" <?php echo $method === 'online' ? 'selected' : '' ?>>Online</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Reference / Transaction No. (optional)</label>
                    <input type="text" name="reference_no" class="form-control" maxlength="100" value="<?php echo admin_esc($reference_no) ?>">
                </div>
                <div class="form-group col-md-8">
                    <label>Note (optional)</label>
                    <input type="text" name="note" class="form-control" value="<?php echo admin_esc($note) ?>">
                </div>
            </div>
            <p class="text-muted small">This records a payment already collected (marked <strong>Paid</strong> immediately). To verify a payment a customer made online/at booking time, use the Payments list instead.</p>
            <button type="submit" class="btn btn-primary" <?php echo empty($bookings) ? 'disabled' : '' ?>><i class="fas fa-save"></i> Save Payment</button>
            <a href="payments.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
