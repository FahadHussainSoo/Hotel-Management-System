<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Amenities';
$errors = [];

// ---- Add a new amenity ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        admin_flash_set('error', 'Your session expired. Please try again.');
        header('Location: amenities.php');
        exit;
    }
    $name = admin_clean($_POST['name'] ?? '');
    if ($name === '') {
        admin_flash_set('error', 'Amenity name cannot be empty.');
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO amenities (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $inserted = $stmt->affected_rows > 0;
        $stmt->close();
        admin_flash_set($inserted ? 'success' : 'error', $inserted ? 'Amenity added.' : "\"$name\" already exists.");
    }
    header('Location: amenities.php');
    exit;
}

// ---- Delete an amenity ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        admin_flash_set('error', 'Your session expired. Please try again.');
        header('Location: amenities.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM room_category_amenities WHERE amenity_id = " . (int)$id);
        $stmt = $conn->prepare("DELETE FROM amenities WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        admin_flash_set('success', 'Amenity removed.');
    }
    header('Location: amenities.php');
    exit;
}

$amenities = $conn->query(
    "SELECT a.id, a.name, COUNT(rca.category_id) AS used_by
     FROM amenities a
     LEFT JOIN room_category_amenities rca ON rca.amenity_id = a.id
     GROUP BY a.id, a.name
     ORDER BY a.name"
)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h5>Add Amenity</h5>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <input type="text" name="name" class="form-control" placeholder="e.g. Free Wi-Fi" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Name</th><th>Used by</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($amenities)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No amenities yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($amenities as $a): ?>
                        <tr>
                            <td><?php echo admin_esc($a['name']) ?></td>
                            <td><?php echo (int)$a['used_by'] ?> room type(s)</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-amenity" data-id="<?php echo (int)$a['id'] ?>" data-name="<?php echo admin_esc($a['name']) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form id="delete_amenity_form" method="POST" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_amenity_id">
</form>

<script>
$('.btn-delete-amenity').on('click', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');
    adminConfirm('Delete amenity "' + name + '"? It will be removed from any room types using it.', function () {
        $('#delete_amenity_id').val(id);
        $('#delete_amenity_form').submit();
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
