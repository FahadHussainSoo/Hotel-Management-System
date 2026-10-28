<?php
/**
 * admin/user_form.php
 *
 * Create or edit a staff/admin account. Admin-only. The admin sets
 * the username and password directly here - this is how staff
 * accounts get created, not a public signup page (see
 * database/staff_management_update.sql for why).
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();
admin_require_admin_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$is_edit = $id > 0;
$is_self = $is_edit && $id === (int)$_SESSION['admin_id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name     = admin_clean($_POST['name'] ?? '');
    $username = admin_clean($_POST['username'] ?? '');
    $type     = (int)($_POST['type'] ?? 2);
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (!in_array($type, [1, 2], true)) $type = 2;
    if ($is_self) $type = 1; // can't demote yourself out of admin from this form

    if ($name === '' || strlen($name) < 2) $errors[] = 'Please enter a full name.';
    if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, . _ - only).';
    }
    // Password required for new accounts; optional on edit (blank = keep current).
    if (!$is_edit || $password !== '' || $confirm !== '') {
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $check->bind_param('si', $username, $id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $errors[] = "Username \"$username\" is already taken.";
        }
        $check->close();
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, type = ?, password = ? WHERE id = ?');
                $stmt->bind_param('ssisi', $name, $username, $type, $hash, $id);
            } else {
                $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, type = ? WHERE id = ?');
                $stmt->bind_param('ssii', $name, $username, $type, $id);
            }
            $stmt->execute();
            $stmt->close();
            if ($is_self) {
                // Keep the session label in sync so the sidebar/name shown doesn't go stale.
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_username'] = $username;
            }
            admin_flash_set('success', 'Account updated.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created_by = (int)$_SESSION['admin_id'];
            $stmt = $conn->prepare('INSERT INTO users (name, username, password, type, status, created_by) VALUES (?, ?, ?, ?, 1, ?)');
            $stmt->bind_param('sssii', $name, $username, $hash, $type, $created_by);
            $stmt->execute();
            $stmt->close();
            admin_flash_set('success', ($type === 1 ? 'Admin' : 'Staff') . ' account created.');
        }
        header('Location: users.php');
        exit;
    }
}

$user = ['id' => 0, 'name' => '', 'username' => '', 'type' => 2];
if ($is_edit) {
    $stmt = $conn->prepare('SELECT id, name, username, type FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        admin_flash_set('error', 'Account not found.');
        header('Location: users.php');
        exit;
    }
    $user = $found;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['name'] = $_POST['name'] ?? $user['name'];
    $user['username'] = $_POST['username'] ?? $user['username'];
    $user['type'] = $_POST['type'] ?? $user['type'];
}

$page_title = $is_edit ? 'Edit Account' : 'Add Staff';
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
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
            <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo (int)$id ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo admin_esc($user['name']) ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo admin_esc($user['username']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Role</label>
                    <select name="type" class="form-control" <?php echo $is_self ? 'disabled' : '' ?>>
                        <option value="2" <?php echo (int)$user['type'] === 2 ? 'selected' : '' ?>>Staff (restricted access)</option>
                        <option value="1" <?php echo (int)$user['type'] === 1 ? 'selected' : '' ?>>Admin (full access)</option>
                    </select>
                    <?php if ($is_self): ?>
                    <small class="form-text text-muted">You can't change your own role here.</small>
                    <input type="hidden" name="type" value="1">
                    <?php endif; ?>
                </div>
                <div class="form-group col-md-4">
                    <label>Password <?php echo $is_edit ? '<small class="text-muted">(leave blank to keep current)</small>' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?php echo $is_edit ? '' : 'required' ?> autocomplete="new-password">
                </div>
                <div class="form-group col-md-4">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" <?php echo $is_edit ? '' : 'required' ?> autocomplete="new-password">
                </div>
            </div>

            <div class="alert alert-info small">
                Staff accounts can manage Room Types, Rooms, Amenities, Bookings, Customers, and Messages.
                They cannot access Staff management, Payments, or Reports.
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            <a href="users.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
