<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../bookings_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Customer Details';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    admin_flash_set('error', 'Customer not found.');
    header('Location: customers.php');
    exit;
}

$stmt = $conn->prepare(
    "SELECT c.*, rc.name AS room_type_name, r.room AS room_number
     FROM checked c
     LEFT JOIN rooms r ON r.id = c.room_id
     LEFT JOIN room_categories rc ON rc.id = r.category_id
     WHERE c.booked_cid = ?
     ORDER BY c.date_updated DESC"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_spent = 0.0;
foreach ($bookings as $b) {
    if ($b['booking_status'] === 'approved') $total_spent += (float)$b['total_price'];
}

$csrf = admin_csrf_token();
include __DIR__ . '/header.php';
?>

<a href="customers.php" class="btn btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Customers</a>

<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h5><?php echo admin_esc($customer['full_name']) ?></h5>
                <table class="table table-sm mb-3">
                    <tr><th style="width:35%">Email</th><td><?php echo admin_esc($customer['email']) ?></td></tr>
                    <tr><th>Phone</th><td><?php echo admin_esc($customer['phone']) ?></td></tr>
                    <tr><th>Joined</th><td><?php echo date('M j, Y', strtotime($customer['created_at'])) ?></td></tr>
                    <tr><th>Bookings</th><td><?php echo count($bookings) ?></td></tr>
                    <tr class="font-weight-bold"><th>Total Spent</th><td>$<?php echo number_format($total_spent, 2) ?></td></tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php if ((int)$customer['status'] === 1): ?>
                            <span class="badge badge-success">Active</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <button type="button" class="btn btn-block btn-outline-<?php echo (int)$customer['status'] === 1 ? 'warning' : 'success' ?>" id="btnToggleCustomer">
                    <?php echo (int)$customer['status'] === 1 ? 'Disable Account' : 'Enable Account' ?>
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-body table-responsive">
                <h5>Booking History</h5>
                <table class="table table-sm table-hover">
                    <thead>
                        <tr><th>Ref No</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No bookings yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($bookings as $b): $bs = booking_status_meta($b['booking_status']); ?>
                        <tr>
                            <td><?php echo admin_esc($b['ref_no']) ?></td>
                            <td><?php echo admin_esc($b['room_type_name'] ?? '(deleted)') ?></td>
                            <td><?php echo date('M j', strtotime($b['date_in'])) ?> &rarr; <?php echo date('M j, Y', strtotime($b['date_out'])) ?></td>
                            <td>$<?php echo number_format((float)$b['total_price'], 2) ?></td>
                            <td><span class="badge <?php echo $bs['class'] ?>"><?php echo $bs['label'] ?></span></td>
                            <td><a href="booking_details.php?id=<?php echo (int)$b['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#btnToggleCustomer').on('click', function () {
    adminConfirm('Change this customer\'s account status?', function () {
        $.ajax({
            url: 'customer_toggle_status.php',
            method: 'POST',
            data: { id: <?php echo (int)$id ?>, csrf_token: <?php echo json_encode($csrf) ?> },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                Swal.fire({icon: 'error', title: 'Failed', text: resp.message});
            }
        }).fail(function () {
            Swal.fire({icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.'});
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
