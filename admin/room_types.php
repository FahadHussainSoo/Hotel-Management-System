<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../rooms_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Room Types';

$res = $conn->query(
    "SELECT rc.*,
        (SELECT COUNT(*) FROM rooms r WHERE r.category_id = rc.id) AS room_count
     FROM room_categories rc
     ORDER BY rc.name"
);
$types = $res->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="mb-3">
    <a href="room_type_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Room Type</a>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price / Night</th>
                    <th>Capacity</th>
                    <th>Amenities</th>
                    <th>Rooms</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                <tr><td colspan="7" class="text-center text-muted">No room types yet. Add your first one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($types as $t):
                    $images = rooms_category_images($conn, $t['id'], $t['cover_img']);
                    $amenities = rooms_category_amenities($conn, $t['id']);
                ?>
                <tr>
                    <td><img src="../assets/img/<?php echo admin_esc($images[0] ?? '') ?>" class="thumb-img" alt=""></td>
                    <td><?php echo admin_esc($t['name']) ?></td>
                    <td>$<?php echo number_format($t['price'], 2) ?></td>
                    <td><?php echo (int)$t['capacity'] ?> guests</td>
                    <td><?php echo count($amenities) ?> assigned</td>
                    <td><?php echo (int)$t['room_count'] ?></td>
                    <td>
                        <a href="room_type_form.php?id=<?php echo (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-type" data-id="<?php echo (int)$t['id'] ?>" data-name="<?php echo admin_esc($t['name']) ?>"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="delete_type_form" method="POST" action="room_type_delete.php" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
    <input type="hidden" name="id" id="delete_type_id">
</form>

<script>
$('.btn-delete-type').on('click', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');
    adminConfirm('Delete room type "' + name + '"? This cannot be undone.', function () {
        $('#delete_type_id').val(id);
        $('#delete_type_form').submit();
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
