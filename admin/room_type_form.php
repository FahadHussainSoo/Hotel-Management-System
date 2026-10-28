<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../rooms_functions.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$is_edit = $id > 0;

$errors = [];

// ---- Handle form submission (add or edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name = admin_clean($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $capacity = (int)($_POST['capacity'] ?? 0);
    $description = admin_clean($_POST['description'] ?? '');
    $amenity_ids = isset($_POST['amenities']) && is_array($_POST['amenities'])
        ? array_map('intval', $_POST['amenities'])
        : [];

    if ($name === '') $errors[] = 'Room type name is required.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if ($capacity < 1) $errors[] = 'Capacity must be at least 1 guest.';

    $upload = ['filenames' => [], 'errors' => []];
    if (!empty($_FILES['images']['name'][0] ?? '')) {
        $upload = admin_handle_image_uploads($_FILES['images']);
        foreach ($upload['errors'] as $e) $errors[] = $e;
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare(
                "UPDATE room_categories SET name = ?, price = ?, capacity = ?, description = ? WHERE id = ?"
            );
            if ($stmt === false) {
                die("Database query failed: " . $conn->error);
            }
            $stmt->bind_param('sdisi', $name, $price, $capacity, $description, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // cover_img is kept in sync below once we know the first image.
            $stmt = $conn->prepare(
                "INSERT INTO room_categories (name, price, capacity, description, cover_img) VALUES (?, ?, ?, ?, '')"
            );
            if ($stmt === false) {
                die("Database query failed: " . $conn->error);
            }
            $stmt->bind_param('sdis', $name, $price, $capacity, $description);
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
        }

        // Save newly uploaded gallery images.
        if (!empty($upload['filenames'])) {
            $max_order = 0;
            $r = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) m FROM room_category_images WHERE category_id = ?");
            $r->bind_param('i', $id);
            $r->execute();
            $max_order = (int)$r->get_result()->fetch_assoc()['m'];
            $r->close();

            $ins = $conn->prepare("INSERT INTO room_category_images (category_id, image_path, sort_order) VALUES (?, ?, ?)");
            foreach ($upload['filenames'] as $filename) {
                $max_order++;
                $ins->bind_param('isi', $id, $filename, $max_order);
                $ins->execute();
            }
            $ins->close();

            // Keep the legacy single cover_img column in sync with the
            // first gallery image so home.php / older queries keep working.
            $first = $conn->query("SELECT image_path FROM room_category_images WHERE category_id = $id ORDER BY sort_order, id LIMIT 1")->fetch_assoc();
            if ($first) {
                $u = $conn->prepare("UPDATE room_categories SET cover_img = ? WHERE id = ?");
                $u->bind_param('si', $first['image_path'], $id);
                $u->execute();
                $u->close();
            }
        }

        // Sync amenities (replace the full set each save).
        $conn->query("DELETE FROM room_category_amenities WHERE category_id = " . (int)$id);
        if (!empty($amenity_ids)) {
            $ins = $conn->prepare("INSERT IGNORE INTO room_category_amenities (category_id, amenity_id) VALUES (?, ?)");
            foreach ($amenity_ids as $aid) {
                $ins->bind_param('ii', $id, $aid);
                $ins->execute();
            }
            $ins->close();
        }

        admin_flash_set('success', $is_edit ? 'Room type updated.' : 'Room type created.');
        header('Location: room_types.php');
        exit;
    }
}

// ---- Load data for the form (edit mode, or after a failed submit) ----
$room = ['id' => 0, 'name' => '', 'price' => '', 'capacity' => 2, 'description' => ''];
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM room_categories WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        die("Database query failed: " . $conn->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        admin_flash_set('error', 'Room type not found.');
        header('Location: room_types.php');
        exit;
    }
    $room = $found;
}
// Re-populate from the failed POST, if any, so the admin doesn't lose their input.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room['name'] = $_POST['name'] ?? $room['name'];
    $room['price'] = $_POST['price'] ?? $room['price'];
    $room['capacity'] = $_POST['capacity'] ?? $room['capacity'];
    $room['description'] = $_POST['description'] ?? $room['description'];
}

$existing_images = [];
if ($is_edit) {
    $r = $conn->prepare("SELECT id, image_path FROM room_category_images WHERE category_id = ? ORDER BY sort_order, id");
    $r->bind_param('i', $id);
    $r->execute();
    $existing_images = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();
}
$selected_amenities = [];
if ($is_edit) {
    $r = $conn->prepare("SELECT amenity_id FROM room_category_amenities WHERE category_id = ?");
    $r->bind_param('i', $id);
    $r->execute();
    $res = $r->get_result();
    while ($row = $res->fetch_assoc()) $selected_amenities[] = (int)$row['amenity_id'];
    $r->close();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amenities'])) {
    $selected_amenities = array_map('intval', (array)$_POST['amenities']);
}

$all_amenities = $conn->query("SELECT id, name FROM amenities ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title = $is_edit ? 'Edit Room Type' : 'Add Room Type';
include __DIR__ . '/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?php echo admin_esc($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
            <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo (int)$id ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Room Type Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo admin_esc($room['name']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Price / Night ($)</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?php echo admin_esc($room['price']) ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Capacity (guests)</label>
                    <input type="number" min="1" max="20" name="capacity" class="form-control" value="<?php echo admin_esc($room['capacity']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo admin_esc($room['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Amenities</label>
                <div class="border rounded p-2">
                    <?php foreach ($all_amenities as $a): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="amenities[]" value="<?php echo (int)$a['id'] ?>" id="amenity_<?php echo (int)$a['id'] ?>" <?php echo in_array((int)$a['id'], $selected_amenities, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="amenity_<?php echo (int)$a['id'] ?>"><?php echo admin_esc($a['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($all_amenities)): ?>
                    <span class="text-muted">No amenities defined yet. <a href="amenities.php">Add some</a>.</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($existing_images)): ?>
            <div class="form-group">
                <label>Current Images</label><br>
                <?php foreach ($existing_images as $img): ?>
                <span class="d-inline-block text-center mr-2 mb-2">
                    <img src="../assets/img/<?php echo admin_esc($img['image_path']) ?>" class="gallery-thumb d-block"><br>
                    <a href="room_type_image_delete.php?id=<?php echo (int)$img['id'] ?>&category_id=<?php echo (int)$id ?>&csrf_token=<?php echo urlencode(admin_csrf_token()) ?>" class="btn btn-sm btn-outline-danger btn-block mt-1" onclick="return confirm('Remove this image?')"><i class="fas fa-times"></i></a>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Upload Images</label>
                <input type="file" name="images[]" class="form-control-file" multiple accept="image/*">
                <small class="form-text text-muted">JPG, PNG, GIF or WEBP, up to 5MB each. The first image becomes the listing cover photo.</small>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Room Type</button>
            <a href="room_types.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
