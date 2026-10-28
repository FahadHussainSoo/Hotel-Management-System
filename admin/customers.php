<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Customers';

$keyword = trim((string)($_GET['keyword'] ?? ''));

$sql = "SELECT cu.*,
            (SELECT COUNT(*) FROM checked c WHERE c.booked_cid = cu.id) AS booking_count,
            (SELECT COALESCE(SUM(c.total_price),0) FROM checked c WHERE c.booked_cid = cu.id AND c.booking_status = 'approved') AS total_spent
        FROM customers cu WHERE 1=1";
$params = [];
$types = '';
if ($keyword !== '') {
    $sql .= " AND (cu.full_name LIKE ? OR cu.email LIKE ? OR cu.phone LIKE ?)";
    $like = '%' . $keyword . '%';
    $types .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY cu.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <label class="mr-2 mb-0">Search</label>
            <input type="text" name="keyword" class="form-control mr-2" placeholder="Name, email, or phone" value="<?php echo admin_esc($keyword) ?>" style="width:280px;">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle" id="customersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Bookings</th>
                    <th>Total Spent</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="8" class="text-center text-muted">No customers match your search.</td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?php echo admin_esc($c['full_name']) ?></td>
                    <td><?php echo admin_esc($c['email']) ?></td>
                    <td><?php echo admin_esc($c['phone']) ?></td>
                    <td><?php echo (int)$c['booking_count'] ?></td>
                    <td>$<?php echo number_format((float)$c['total_spent'], 2) ?></td>
                    <td>
                        <?php if ((int)$c['status'] === 1): ?>
                        <span class="badge badge-success">Active</span>
                        <?php else: ?>
                        <span class="badge badge-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($c['created_at'])) ?></td>
                    <td><a href="customer_details.php?id=<?php echo (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#customersTable').DataTable({ order: [[6, 'desc']] });
</script>

<?php include __DIR__ . '/footer.php'; ?>
