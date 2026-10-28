<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$id = (int)($_GET['id'] ?? 0);
$category_id = (int)($_GET['category_id'] ?? 0);

if (!admin_csrf_check($_GET['csrf_token'] ?? '')) {
    admin_flash_set('error', 'Your session expired. Please try again.');
    header('Location: room_type_form.php?id=' . $category_id);
    exit;
}

if ($id <= 0 || $category_id <= 0) {
    header('Location: room_types.php');
    exit;
}

$stmt = $conn->prepare("SELECT image_path FROM room_category_images WHERE id = ? AND category_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $category_id);
$stmt->execute();
$img = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($img) {
    $path = __DIR__ . '/../assets/img/' . basename($img['image_path']);
    if (is_file($path)) {
        @unlink($path);
    }
    $stmt = $conn->prepare("DELETE FROM room_category_images WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // If the deleted image was the cover, promote the next remaining
    // gallery image (if any) to keep cover_img in sync.
    $cover_stmt = $conn->prepare("SELECT cover_img FROM room_categories WHERE id = ?");
    $cover_stmt->bind_param('i', $category_id);
    $cover_stmt->execute();
    $current_cover = $cover_stmt->get_result()->fetch_assoc()['cover_img'] ?? '';
    $cover_stmt->close();

    if ($current_cover === $img['image_path']) {
        $next = $conn->query(
            "SELECT image_path FROM room_category_images WHERE category_id = " . (int)$category_id . " ORDER BY sort_order, id LIMIT 1"
        )->fetch_assoc();
        $new_cover = $next['image_path'] ?? '';
        $u = $conn->prepare("UPDATE room_categories SET cover_img = ? WHERE id = ?");
        $u->bind_param('si', $new_cover, $category_id);
        $u->execute();
        $u->close();
    }

    admin_flash_set('success', 'Image removed.');
} else {
    admin_flash_set('error', 'Image not found.');
}

header('Location: room_type_form.php?id=' . $category_id);
exit;
