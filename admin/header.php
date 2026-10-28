<?php
/**
 * admin/header.php
 *
 * Shared layout top for every admin page. The calling page must
 * define $page_title before including this file, and should
 * require_once db_connect.php + functions.php + call
 * admin_require_login() itself before any output (same pattern
 * customer/functions.php uses).
 */
if (!isset($page_title)) {
    $page_title = 'Admin';
}
$flash = admin_flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo admin_esc($page_title) ?> - Admin - Hotel Management System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
    <link href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11/sweetalert2.all.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        // Defined here (in <head>, loaded before any page body content) so
        // that every admin page's inline <script> block — which runs
        // immediately, not on a document-ready handler — can call this
        // regardless of where in the body it appears.
        function adminConfirm(message, onConfirm) {
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (result.isConfirmed) onConfirm();
            });
        }
    </script>
    <style>
        body { background: #f4f6f9; }
        .admin-sidebar { min-height: 100vh; background: #1c2b39; }
        .admin-sidebar a { color: #c7d0d8; display: block; padding: 12px 20px; text-decoration: none; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background: #14202b; color: #fff; }
        .admin-sidebar .brand { color: #fff; font-weight: bold; padding: 18px 20px; display: block; border-bottom: 1px solid #2a3d4d; }
        .admin-content { padding: 24px; }
        .thumb-img { width: 60px; height: 45px; object-fit: cover; border-radius: 4px; }
        .gallery-thumb { width: 90px; height: 70px; object-fit: cover; border-radius: 4px; margin: 3px; }
    </style>
    <!-- Phase 6 UI polish (additive: responsive sidebar, hover effects, spacing) -->
    <link rel="stylesheet" href="polish.css">
</head>
<body>
<div class="admin-sidebar-backdrop"></div>
<div class="d-flex">
    <div class="admin-sidebar" style="width:220px;">
        <a class="brand" href="index.php"><i class="fas fa-hotel"></i> Hotel Admin</a>
        <a href="index.php" class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
        <a href="room_types.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['room_types.php', 'room_type_form.php']) ? 'active' : '' ?>"><i class="fas fa-layer-group mr-2"></i>Room Types</a>
        <a href="rooms.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['rooms.php', 'room_form.php']) ? 'active' : '' ?>"><i class="fas fa-door-open mr-2"></i>Rooms</a>
        <a href="amenities.php" class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'amenities.php' ? 'active' : '' ?>"><i class="fas fa-concierge-bell mr-2"></i>Amenities</a>
        <a href="bookings.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['bookings.php', 'booking_details.php']) ? 'active' : '' ?>"><i class="fas fa-calendar-check mr-2"></i>Bookings</a>
        <a href="customers.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['customers.php', 'customer_details.php']) ? 'active' : '' ?>"><i class="fas fa-users mr-2"></i>Customers</a>
        <?php if (admin_is_admin_role()): ?>
        <a href="payments.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['payments.php', 'payment_form.php']) ? 'active' : '' ?>"><i class="fas fa-money-bill-wave mr-2"></i>Payments</a>
        <?php endif; ?>
        <a href="messages.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['messages.php', 'message_details.php']) ? 'active' : '' ?>"><i class="fas fa-envelope mr-2"></i>Messages<?php
            if (isset($conn) && $conn instanceof mysqli) {
                $__new_msgs = (int)$conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'")->fetch_assoc()['c'];
                if ($__new_msgs > 0) echo ' <span class="badge badge-danger">' . $__new_msgs . '</span>';
            }
        ?></a>
        <?php if (admin_is_admin_role()): ?>
        <a href="reports.php" class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-line mr-2"></i>Reports</a>
        <a href="users.php" class="<?php echo in_array(basename($_SERVER['SCRIPT_NAME']), ['users.php', 'user_form.php']) ? 'active' : '' ?>"><i class="fas fa-user-shield mr-2"></i>Staff</a>
        <?php endif; ?>
        <a href="../index.php" target="_blank"><i class="fas fa-external-link-alt mr-2"></i>View Site</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout (<?php echo admin_esc($_SESSION['admin_name'] ?? '') ?>)</a>
    </div>
    <div class="flex-fill">
        <div class="admin-content">
            <button type="button" class="admin-sidebar-toggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <h3 class="mb-4"><?php echo admin_esc($page_title) ?></h3>
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : admin_esc($flash['type']) ?>"><?php echo admin_esc($flash['message']) ?></div>
            <?php endif; ?>
