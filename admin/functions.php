<?php
/**
 * admin/functions.php
 *
 * Shared helpers for the admin area. Included by every admin page,
 * mirroring the style already used by customer/functions.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** True if an admin/staff user is currently logged in. */
function admin_is_logged_in() {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

/** Guard for pages that require a logged-in admin. Call before any HTML output. */
function admin_require_login($redirect_to = 'login.php') {
    if (!admin_is_logged_in()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/** True if the logged-in user is a full admin (type 1), not a restricted staff account (type 2). */
function admin_is_admin_role() {
    return admin_is_logged_in() && (int)($_SESSION['admin_type'] ?? 0) === 1;
}

/**
 * Guard for pages that only a full admin (not staff) may access, e.g.
 * Staff management, Payments, Reports. Call after admin_require_login(),
 * before any HTML output. A staff account hitting this gets bounced to
 * the dashboard with a flash message rather than a raw error page.
 */
function admin_require_admin_role($redirect_to = 'index.php') {
    if (!admin_is_admin_role()) {
        admin_flash_set('danger', "You don't have permission to access that page.");
        header('Location: ' . $redirect_to);
        exit;
    }
}

/** Trim + strip tags for values that will be stored in the database. */
function admin_clean($value) {
    return trim(strip_tags((string)($value ?? '')));
}

/** Escape a value for safe HTML output. */
function admin_esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** One CSRF token per session, used on every state-changing admin form. */
function admin_csrf_token() {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_check($token) {
    return !empty($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], (string)$token);
}

/** Standard JSON response used by the admin AJAX processors. */
function admin_json($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => (bool)$success, 'message' => $message], $extra));
    exit;
}

/** Queue a one-time flash message shown after a redirect (PRG pattern). */
function admin_flash_set($type, $message) {
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

/** Read + clear the queued flash message, if any. */
function admin_flash_get() {
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}

/**
 * Validate and move one or more uploaded image files into
 * assets/img/ (the same folder already used by room_categories.cover_img).
 *
 * @param array $file A single entry from $_FILES (e.g. $_FILES['images'])
 *                     using PHP's standard multi-file array shape.
 * @return array{filenames:string[], errors:string[]}
 */
function admin_handle_image_uploads($file) {
    $result = ['filenames' => [], 'errors' => []];

    if (empty($file) || !isset($file['name'])) {
        return $result;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB per image
    $dest_dir = __DIR__ . '/../assets/img/';

    // Normalise into a flat list of single-file arrays, whether the
    // input field was a single <input> or a multi-file <input multiple>.
    $names = is_array($file['name']) ? $file['name'] : [$file['name']];
    $count = count($names);

    for ($i = 0; $i < $count; $i++) {
        $name  = is_array($file['name']) ? $file['name'][$i] : $file['name'];
        $tmp   = is_array($file['tmp_name']) ? $file['tmp_name'][$i] : $file['tmp_name'];
        $error = is_array($file['error']) ? $file['error'][$i] : $file['error'];
        $size  = is_array($file['size']) ? $file['size'][$i] : $file['size'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue; // this slot was left empty, not an error
        }
        if ($error !== UPLOAD_ERR_OK) {
            $result['errors'][] = "Upload failed for \"$name\" (error code $error).";
            continue;
        }
        if ($size > $max_size) {
            $result['errors'][] = "\"$name\" is larger than 5MB.";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $result['errors'][] = "\"$name\" is not an accepted image type (jpg, png, gif, webp).";
            continue;
        }

        $mime = @mime_content_type($tmp);
        if ($mime !== false && !in_array($mime, $allowed_mime, true)) {
            $result['errors'][] = "\"$name\" does not look like a valid image file.";
            continue;
        }

        $new_name = 'room_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dest_dir . $new_name)) {
            $result['errors'][] = "Could not save \"$name\" to the server.";
            continue;
        }

        $result['filenames'][] = $new_name;
    }

    return $result;
}
