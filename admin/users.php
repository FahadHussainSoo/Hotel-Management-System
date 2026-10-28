<?php
/**
 * admin/users.php
 *
 * Staff management. Admin-only (admin_require_admin_role): a staff
 * account (type 2) cannot view or manage other accounts, and cannot
 * promote itself. Only a full admin can create staff logins - there
 * is deliberately no public self-signup for staff (see
 * database/staff_management_update.sql for why).
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();
admin_require_admin_role();

$page_title = 'Staff';

$users = $conn->query(
    "SELECT id, name, username, type, status, created_at FROM users ORDER BY type ASC, name ASC"
)->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Admin accounts have full access. Staff accounts have restricted access (no Staff management, Payments, or Reports).</p>
    <a href="user_form.php" class="btn btn-primary"><i class="fas fa-user-plus mr-1"></i> Add Staff</a>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center text-muted">No accounts found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo admin_esc($u['name']) ?></td>
                    <td><?php echo admin_esc($u['username']) ?></td>
                    <td>
                        <?php if ((int)$u['type'] === 1): ?>
                            <span class="badge badge-primary">Admin</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Staff</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$u['status'] === 1): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo admin_esc(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td>
                        <a href="user_form.php?id=<?php echo (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['admin_id']): ?>
                        <button type="button"
                                class="btn btn-sm <?php echo (int)$u['status'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-toggle-status"
                                data-id="<?php echo (int)$u['id'] ?>"
                                data-name="<?php echo admin_esc($u['name']) ?>"
                                data-active="<?php echo (int)$u['status'] ?>">
                            <?php if ((int)$u['status'] === 1): ?>
                                <i class="fas fa-user-slash"></i>
                            <?php else: ?>
                                <i class="fas fa-user-check"></i>
                            <?php endif; ?>
                        </button>
                        <?php else: ?>
                        <span class="text-muted small">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="toggle_status_form" method="POST" action="user_toggle_status.php" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo admin_esc(admin_csrf_token()) ?>">
    <input type="hidden" name="id" id="toggle_status_id">
</form>

<script>
$('.btn-toggle-status').on('click', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var isActive = $(this).data('active') == 1;
    var msg = isActive
        ? 'Disable "' + name + '"? They will no longer be able to log in.'
        : 'Re-enable "' + name + '"? They will be able to log in again.';
    adminConfirm(msg, function () {
        $('#toggle_status_id').val(id);
        $('#toggle_status_form').submit();
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
