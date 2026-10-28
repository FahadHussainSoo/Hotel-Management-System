<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Rooms';

$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$sql = "SELECT r.*, rc.name AS category_name
        FROM rooms r
        LEFT JOIN room_categories rc ON rc.id = r.category_id";
$params = [];
$types = '';
if ($category_filter > 0) {
    $sql .= " WHERE r.category_id = ?";
    $types .= 'i';
    $params[] = $category_filter;
}
$sql .= " ORDER BY rc.name, r.room";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$all_categories = $conn->query("SELECT id, name FROM room_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="room_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Room</a>
    <form method="GET" class="form-inline">
        <label class="mr-2 mb-0">Filter by type</label>
        <select name="category_id" class="form-control mr-2" onchange="this.form.submit()">
            <option value="0">All types</option>
            <?php foreach ($all_categories as $c): ?>
            <option value="<?php echo (int)$c['id'] ?>" <?php echo $category_filter === (int)$c['id'] ? 'selected' : '' ?>><?php echo admin_esc($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle" id="roomsTable">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Room Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                <tr><td colspan="4" class="text-center text-muted">No rooms yet. Add your first one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><?php echo admin_esc($r['room']) ?></td>
                    <td><?php echo admin_esc($r['category_name'] ?? '(deleted type)') ?></td>
                    <td>
                        <?php if ((int)$r['status'] === 0): ?>
                        <span class="badge badge-success">Enabled</span>
                        <?php else: ?>
                        <span class="badge badge-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="room_form.php?id=<?php echo (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-<?php echo (int)$r['status'] === 0 ? 'warning' : 'success' ?> btn-toggle" data-id="<?php echo (int)$r['id'] ?>">
                            <?php echo (int)$r['status'] === 0 ? 'Disable' : 'Enable' ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-room" data-id="<?php echo (int)$r['id'] ?>" data-name="<?php echo admin_esc($r['room']) ?>"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="delete_room_form" method="POST" action="room_delete.php" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
    <input type="hidden" name="id" id="delete_room_id">
</form>

<script>
$('#roomsTable').DataTable({ order: [[1, 'asc'], [0, 'asc']] });

var toggleCsrf = <?php echo json_encode(admin_csrf_token()) ?>;

$('.btn-toggle').on('click', function () {
    var $btn = $(this);
    var id = $btn.data('id');
    $.ajax({
        url: 'room_toggle_status.php',
        method: 'POST',
        data: { id: id, csrf_token: toggleCsrf },
        dataType: 'json'
    }).done(function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message);
        }
    }).fail(function () {
        alert('Something went wrong. Please try again.');
    });
});

$('.btn-delete-room').on('click', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');
    adminConfirm('Delete room "' + name + '"? This cannot be undone.', function () {
        $('#delete_room_id').val(id);
        $('#delete_room_form').submit();
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
