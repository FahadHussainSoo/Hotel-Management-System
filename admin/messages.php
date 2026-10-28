<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Contact Messages';

$status_filter = trim((string)($_GET['status'] ?? ''));
$valid_statuses = ['new', 'read', 'replied'];

$sql = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];
$types = '';
if (in_array($status_filter, $valid_statuses, true)) {
    $sql .= " AND status = ?";
    $types .= 's';
    $params[] = $status_filter;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <label class="mr-2 mb-0">Status</label>
            <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($valid_statuses as $s): ?>
                <option value="<?php echo $s ?>" <?php echo $status_filter === $s ? 'selected' : '' ?>><?php echo ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle" id="messagesTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                <tr><td colspan="6" class="text-center text-muted">No messages.</td></tr>
                <?php endif; ?>
                <?php foreach ($messages as $m):
                    $badge = ['new' => 'badge-danger', 'read' => 'badge-secondary', 'replied' => 'badge-success'][$m['status']] ?? 'badge-light';
                ?>
                <tr class="<?php echo $m['status'] === 'new' ? 'font-weight-bold' : '' ?>">
                    <td><?php echo date('M j, Y g:i A', strtotime($m['created_at'])) ?></td>
                    <td><?php echo admin_esc($m['name']) ?></td>
                    <td><?php echo admin_esc($m['email']) ?></td>
                    <td><?php echo admin_esc($m['subject']) ?></td>
                    <td><span class="badge <?php echo $badge ?>"><?php echo ucfirst($m['status']) ?></span></td>
                    <td><a href="message_details.php?id=<?php echo (int)$m['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#messagesTable').DataTable({ order: [[0, 'desc']] });
</script>

<?php include __DIR__ . '/footer.php'; ?>
