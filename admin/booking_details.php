<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../bookings_functions.php';
require_once __DIR__ . '/../payments_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Booking Details';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT c.*, rc.name AS room_type_name, rc.price AS room_price, r.room AS room_number
     FROM checked c
     LEFT JOIN rooms r ON r.id = c.room_id
     LEFT JOIN room_categories rc ON rc.id = r.category_id
     WHERE c.id = ? LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

$customer = null;
if ($booking && (int)$booking['booked_cid'] > 0) {
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM customers WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $booking['booked_cid']);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$payments = $booking ? payment_get_all_for_booking($conn, $booking['id']) : [];

$csrf = admin_csrf_token();

include __DIR__ . '/header.php';
?>

<a href="bookings.php" class="btn btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Bookings</a>

<?php if (!$booking): ?>
<div class="alert alert-danger">Booking not found.</div>
<?php else:
    $bs = booking_status_meta($booking['booking_status']);
    $os = booking_occupancy_meta($booking['status']);
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="mb-0">Booking #<?php echo admin_esc($booking['ref_no']) ?></h4>
                    <div>
                        <span class="badge <?php echo $bs['class'] ?>"><?php echo $bs['label'] ?></span>
                        <span class="badge <?php echo $os['class'] ?>"><?php echo $os['label'] ?></span>
                    </div>
                </div>

                <?php if (!empty($booking['cancel_reason'])): ?>
                <div class="alert alert-secondary"><strong>Reason on file:</strong> <?php echo admin_esc($booking['cancel_reason']) ?></div>
                <?php endif; ?>

                <table class="table table-sm">
                    <tr><th style="width:30%">Room Type</th><td><?php echo admin_esc($booking['room_type_name'] ?? '(deleted)') ?></td></tr>
                    <tr><th>Room</th><td><?php echo admin_esc($booking['room_number'] ?? 'N/A') ?></td></tr>
                    <tr><th>Check-in</th><td><?php echo date('l, F j, Y', strtotime($booking['date_in'])) ?></td></tr>
                    <tr><th>Check-out</th><td><?php echo date('l, F j, Y', strtotime($booking['date_out'])) ?></td></tr>
                    <tr><th>Nights</th><td><?php echo (int)$booking['nights'] ?></td></tr>
                    <tr><th>Guests</th><td><?php echo (int)$booking['guests'] ?></td></tr>
                    <tr><th>Rate</th><td>$<?php echo number_format((float)$booking['room_price'], 2) ?> / night</td></tr>
                    <tr class="font-weight-bold"><th>Total Price</th><td>$<?php echo number_format((float)$booking['total_price'], 2) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5>Guest / Contact</h5>
                <table class="table table-sm mb-0">
                    <tr><th style="width:30%">Name</th><td><?php echo admin_esc($booking['name']) ?></td></tr>
                    <tr><th>Phone</th><td><?php echo admin_esc($booking['contact_no']) ?></td></tr>
                    <?php if ($customer): ?>
                    <tr><th>Account Email</th><td><?php echo admin_esc($customer['email']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5>Payments</h5>
                <?php if (empty($payments)): ?>
                <p class="text-muted mb-0">No payment record for this booking yet.</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Date</th><th>Method</th><th>Reference No.</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): $pm = payment_status_meta($p['status']); ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                            <td><?php echo admin_esc(payment_method_label($p['method'])) ?></td>
                            <td><?php echo $p['reference_no'] ? admin_esc($p['reference_no']) : '<span class="text-muted">&mdash;</span>' ?></td>
                            <td>$<?php echo number_format((float)$p['amount'], 2) ?></td>
                            <td><span class="badge <?php echo $pm['class'] ?>"><?php echo $pm['label'] ?></span></td>
                            <td><?php echo $p['payment_date'] ? date('M j, Y g:i A', strtotime($p['payment_date'])) : '<span class="text-muted">&mdash;</span>' ?></td>
                            <td>
                                <?php if (payment_can_verify($p)): ?>
                                <button type="button" class="btn btn-sm btn-success btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="paid">Mark Paid</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="failed">Mark Failed</button>
                                <?php elseif (payment_can_refund($p)): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="refunded">Mark Refunded</button>
                                <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <a href="payment_form.php?booking_id=<?php echo (int)$booking['id'] ?>" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-plus"></i> Record Additional Payment</a>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5>Actions</h5>

                <?php if (booking_can_approve_or_reject($booking)): ?>
                <button type="button" class="btn btn-success btn-block mb-2 btn-action" data-action="booking_approve_process.php">
                    <i class="fas fa-check"></i> Approve Booking
                </button>
                <button type="button" class="btn btn-outline-danger btn-block mb-2 btn-action-reason" data-action="booking_reject_process.php" data-title="Reject this booking?" data-placeholder="Reason for rejecting (optional)">
                    <i class="fas fa-times"></i> Reject Booking
                </button>
                <?php endif; ?>

                <?php if (booking_can_check_in($booking)): ?>
                <button type="button" class="btn btn-info btn-block mb-2 btn-action" data-action="booking_checkin_process.php">
                    <i class="fas fa-sign-in-alt"></i> Mark Checked In
                </button>
                <?php endif; ?>

                <?php if (booking_can_check_out($booking)): ?>
                <button type="button" class="btn btn-dark btn-block mb-2 btn-action" data-action="booking_checkout_process.php">
                    <i class="fas fa-sign-out-alt"></i> Mark Checked Out
                </button>
                <?php endif; ?>

                <?php if (booking_can_cancel($booking)): ?>
                <button type="button" class="btn btn-outline-secondary btn-block mb-2 btn-action-reason" data-action="booking_cancel_process.php" data-title="Cancel this booking?" data-placeholder="Reason for cancelling (optional)">
                    <i class="fas fa-ban"></i> Cancel Booking
                </button>
                <?php endif; ?>

                <?php if (!booking_can_approve_or_reject($booking) && !booking_can_check_in($booking) && !booking_can_check_out($booking) && !booking_can_cancel($booking)): ?>
                <p class="text-muted mb-0">No further actions available for this booking.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var actionCsrf = <?php echo json_encode($csrf) ?>;
var bookingId = <?php echo (int)$booking['id'] ?>;

function runBookingAction(action, extra) {
    var data = Object.assign({ id: bookingId, csrf_token: actionCsrf }, extra || {});
    $.ajax({
        url: action,
        method: 'POST',
        data: data,
        dataType: 'json'
    }).done(function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            Swal.fire({icon: 'error', title: 'Action failed', text: resp.message});
        }
    }).fail(function () {
        Swal.fire({icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.'});
    });
}

$('.btn-action').on('click', function () {
    var action = $(this).data('action');
    adminConfirm('Are you sure you want to do this?', function () {
        runBookingAction(action, {});
    });
});

$('.btn-action-reason').on('click', function () {
    var action = $(this).data('action');
    var title = $(this).data('title');
    var placeholder = $(this).data('placeholder');
    Swal.fire({
        title: title,
        input: 'text',
        inputPlaceholder: placeholder,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (result.isConfirmed) {
            runBookingAction(action, { reason: result.value || '' });
        }
    });
});

$('.btn-payment-action').on('click', function () {
    var paymentId = $(this).data('id');
    var status = $(this).data('status');
    adminConfirm('Mark this payment as ' + status + '?', function () {
        $.ajax({
            url: 'payment_status_update_process.php',
            method: 'POST',
            data: { id: paymentId, status: status, csrf_token: actionCsrf },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                Swal.fire({icon: 'error', title: 'Action failed', text: resp.message});
            }
        }).fail(function () {
            Swal.fire({icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.'});
        });
    });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
