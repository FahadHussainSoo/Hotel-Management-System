<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../payments_functions.php';
admin_require_login();
admin_require_admin_role();

$page_title = 'Payments';

$payments = $conn->query(
    "SELECT p.*, c.ref_no, c.name AS guest_name, c.total_price
     FROM payments p
     LEFT JOIN checked c ON c.id = p.booking_id
     ORDER BY p.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$revenue = (float)$conn->query("SELECT COALESCE(SUM(total_price),0) s FROM checked WHERE booking_status = 'approved'")->fetch_assoc()['s'];
$collected = (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status = 'paid'")->fetch_assoc()['s'];

$csrf = admin_csrf_token();

include __DIR__ . '/header.php';
?>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-light"><div class="card-body"><div class="text-muted">Confirmed Revenue</div><h4>$<?php echo number_format($revenue, 2) ?></h4></div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-light"><div class="card-body"><div class="text-muted">Collected</div><h4 class="text-success">$<?php echo number_format($collected, 2) ?></h4></div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-light"><div class="card-body"><div class="text-muted">Outstanding</div><h4 class="text-danger">$<?php echo number_format(max(0, $revenue - $collected), 2) ?></h4></div></div>
    </div>
</div>

<div class="mb-3">
    <a href="payment_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Record Payment</a>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle" id="paymentsTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Booking Ref</th>
                    <th>Guest</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference No.</th>
                    <th>Status</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="9" class="text-center text-muted">No payments recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $p):
                    $pm = payment_status_meta($p['status']);
                ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                    <td><?php if ($p['ref_no']): ?><a href="booking_details.php?id=<?php echo (int)$p['booking_id'] ?>"><?php echo admin_esc($p['ref_no']) ?></a><?php else: ?><span class="text-muted">(deleted booking)</span><?php endif; ?></td>
                    <td><?php echo admin_esc($p['guest_name'] ?? '') ?></td>
                    <td>$<?php echo number_format((float)$p['amount'], 2) ?></td>
                    <td><span class="badge badge-light border"><?php echo admin_esc(payment_method_label($p['method'])) ?></span></td>
                    <td><?php echo $p['reference_no'] ? admin_esc($p['reference_no']) : '<span class="text-muted">&mdash;</span>' ?></td>
                    <td><span class="badge <?php echo $pm['class'] ?>"><?php echo $pm['label'] ?></span></td>
                    <td><?php echo admin_esc($p['note']) ?></td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-success btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="paid">Mark Paid</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="failed">Mark Failed</button>
                        <?php elseif ($p['status'] === 'paid'): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-payment-action" data-id="<?php echo (int)$p['id'] ?>" data-status="refunded">Mark Refunded</button>
                        <?php else: ?>
                        <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var paymentsCsrf = <?php echo json_encode($csrf) ?>;

$('#paymentsTable').DataTable({ order: [[0, 'desc']], columnDefs: [{ orderable: false, targets: -1 }] });

$('.btn-payment-action').on('click', function () {
    var id = $(this).data('id');
    var status = $(this).data('status');
    adminConfirm('Mark this payment as ' + status + '?', function () {
        $.ajax({
            url: 'payment_status_update_process.php',
            method: 'POST',
            data: { id: id, status: status, csrf_token: paymentsCsrf },
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

<?php include __DIR__ . '/footer.php'; ?>
