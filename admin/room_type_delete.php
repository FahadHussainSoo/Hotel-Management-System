<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: room_types.php');
    exit;
}
if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
    admin_flash_set('error', 'Your session expired. Please try again.');
    header('Location: room_types.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    admin_flash_set('error', 'Invalid room type.');
    header('Location: room_types.php');
    exit;
}

// Safety check: don't silently orphan physical rooms.
$stmt = $conn->prepare("SELECT COUNT(*) c FROM rooms WHERE category_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$room_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

if ($room_count > 0) {
    admin_flash_set('error', "Can't delete this room type — it still has $room_count room(s) assigned to it. Delete or reassign those rooms first.");
    header('Location: room_types.php');
    exit;
}

// Remove gallery image files from disk, then the DB rows.
$stmt = $conn->prepare("SELECT image_path FROM room_category_images WHERE category_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$img_dir = __DIR__ . '/../assets/img/';
while ($row = $res->fetch_assoc()) {
    $path = $img_dir . basename($row['image_path']);
    if (is_file($path)) {
        @unlink($path);
    }
}
$stmt->close();

$conn->query("DELETE FROM room_category_images WHERE category_id = " . (int)$id);
$conn->query("DELETE FROM room_category_amenities WHERE category_id = " . (int)$id);

$stmt = $conn->prepare("DELETE FROM room_categories WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

admin_flash_set('success', 'Room type deleted.');
header('Location: room_types.php');
exit;
