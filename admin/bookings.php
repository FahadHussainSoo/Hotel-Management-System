<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../bookings_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Bookings';

$keyword         = trim((string)($_GET['keyword'] ?? ''));
$booking_status  = trim((string)($_GET['booking_status'] ?? ''));
$occupancy       = $_GET['occupancy'] ?? '';

$valid_booking_statuses = ['pending', 'approved', 'rejected', 'cancelled'];

$sql = "SELECT c.*, rc.name AS room_type_name, r.room AS room_number
        FROM checked c
        LEFT JOIN rooms r ON r.id = c.room_id
        LEFT JOIN room_categories rc ON rc.id = r.category_id
        WHERE 1=1";
$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND (c.ref_no LIKE ? OR c.name LIKE ? OR c.contact_no LIKE ? OR r.room LIKE ?)";
    $like = '%' . $keyword . '%';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (in_array($booking_status, $valid_booking_statuses, true)) {
    $sql .= " AND c.booking_status = ?";
    $types .= 's';
    $params[] = $booking_status;
}
if ($occupancy !== '' && in_array((int)$occupancy, [0, 1, 2], true)) {
    $sql .= " AND c.status = ?";
    $types .= 'i';
    $params[] = (int)$occupancy;
}
$sql .= " ORDER BY c.date_updated DESC, c.id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="form-row align-items-end">
            <div class="col-md-4 mb-2">
                <label class="mb-1">Search</label>
                <input type="text" name="keyword" class="form-control" placeholder="Ref no, guest name, phone, room..." value="<?php echo admin_esc($keyword) ?>">
            </div>
            <div class="col-md-3 mb-2">
                <label class="mb-1">Booking Status</label>
                <select name="booking_status" class="form-control">
                    <option value="">Any status</option>
                    <?php foreach ($valid_booking_statuses as $s): ?>
                    <option value="<?php echo $s ?>" <?php echo $booking_status === $s ? 'selected' : '' ?>><?php echo ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="mb-1">Occupancy</label>
                <select name="occupancy" class="form-control">
                    <option value="">Any</option>
                    <option value="0" <?php echo $occupancy === '0' ? 'selected' : '' ?>>Not Checked In</option>
                    <option value="1" <?php echo $occupancy === '1' ? 'selected' : '' ?>>Checked In</option>
                    <option value="2" <?php echo $occupancy === '2' ? 'selected' : '' ?>>Checked Out</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle" id="bookingsTable">
            <thead>
                <tr>
                    <th>Ref No</th>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Dates</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Booking Status</th>
                    <th>Occupancy</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                <tr><td colspan="9" class="text-center text-muted">No bookings match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($bookings as $b):
                    $bs = booking_status_meta($b['booking_status']);
                    $os = booking_occupancy_meta($b['status']);
                ?>
                <tr>
                    <td><?php echo admin_esc($b['ref_no']) ?></td>
                    <td><?php echo admin_esc($b['name']) ?><br><span class="text-muted small"><?php echo admin_esc($b['contact_no']) ?></span></td>
                    <td><?php echo admin_esc($b['room_type_name'] ?? '(deleted)') ?><br><span class="text-muted small"><?php echo admin_esc($b['room_number'] ?? '') ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($b['date_in'])) ?> &rarr; <?php echo date('M j, Y', strtotime($b['date_out'])) ?><br><span class="text-muted small"><?php echo (int)$b['nights'] ?> night<?php echo (int)$b['nights'] === 1 ? '' : 's' ?></span></td>
                    <td><?php echo (int)$b['guests'] ?></td>
                    <td>$<?php echo number_format((float)$b['total_price'], 2) ?></td>
                    <td><span class="badge <?php echo $bs['class'] ?>"><?php echo $bs['label'] ?></span></td>
                    <td><span class="badge <?php echo $os['class'] ?>"><?php echo $os['label'] ?></span></td>
                    <td><a href="booking_details.php?id=<?php echo (int)$b['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#bookingsTable').DataTable({ order: [], pageLength: 25 });
</script>

<?php include __DIR__ . '/footer.php'; ?>
