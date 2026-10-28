<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$is_edit = $id > 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $room_no = admin_clean($_POST['room'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    if ($room_no === '') $errors[] = 'Room number / name is required.';
    if ($category_id <= 0) $errors[] = 'Please choose a room type.';
    if (!in_array($status, [0, 1], true)) $status = 0;

    // Room numbers should be unique.
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM rooms WHERE room = ? AND id != ? LIMIT 1");
        $check->bind_param('si', $room_no, $id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $errors[] = "Room \"$room_no\" already exists.";
        }
        $check->close();
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE rooms SET room = ?, category_id = ?, status = ? WHERE id = ?");
            $stmt->bind_param('siii', $room_no, $category_id, $status, $id);
            $stmt->execute();
            $stmt->close();
            admin_flash_set('success', 'Room updated.');
        } else {
            $stmt = $conn->prepare("INSERT INTO rooms (room, category_id, status) VALUES (?, ?, ?)");
            $stmt->bind_param('sii', $room_no, $category_id, $status);
            $stmt->execute();
            $stmt->close();
            admin_flash_set('success', 'Room added.');
        }
        header('Location: rooms.php');
        exit;
    }
}

$room = ['id' => 0, 'room' => '', 'category_id' => 0, 'status' => 0];
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        admin_flash_set('error', 'Room not found.');
        header('Location: rooms.php');
        exit;
    }
    $room = $found;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room['room'] = $_POST['room'] ?? $room['room'];
    $room['category_id'] = $_POST['category_id'] ?? $room['category_id'];
    $room['status'] = $_POST['status'] ?? $room['status'];
}

$all_categories = $conn->query("SELECT id, name FROM room_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title = $is_edit ? 'Edit Room' : 'Add Room';
include __DIR__ . '/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?php echo admin_esc($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($all_categories)): ?>
<div class="alert alert-warning">You need to <a href="room_type_form.php">create a room type</a> before adding rooms.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
            <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo (int)$id ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Room Number / Name</label>
                    <input type="text" name="room" class="form-control" placeholder="e.g. Room-101" value="<?php echo admin_esc($room['room']) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Room Type</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($all_categories as $c): ?>
                        <option value="<?php echo (int)$c['id'] ?>" <?php echo (int)$room['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?php echo admin_esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="0" <?php echo (int)$room['status'] === 0 ? 'selected' : '' ?>>Enabled (bookable)</option>
                        <option value="1" <?php echo (int)$room['status'] === 1 ? 'selected' : '' ?>>Disabled (hidden from customers)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" <?php echo empty($all_categories) ? 'disabled' : '' ?>><i class="fas fa-save"></i> Save Room</button>
            <a href="rooms.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
