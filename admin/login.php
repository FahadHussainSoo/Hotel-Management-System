<?php
require_once __DIR__ . '/functions.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}
$csrf = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Hotel Management System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
    <style>
        body { background: #1c2b39; height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 380px; margin: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card shadow">
            <div class="card-body p-4">
                <h4 class="text-center mb-4"><i class="fas fa-hotel"></i> Admin Login</h4>
                <form id="admin_login_form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_esc($csrf) ?>">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div id="login_error" class="alert alert-danger d-none"></div>
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                    <a href="../index.php" class="btn btn-link btn-block">&larr; Back to site</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
    $(function () {
        $('#admin_login_form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type=submit]');
            var $err = $('#login_error').addClass('d-none');
            $btn.prop('disabled', true).text('Logging in...');
            $.ajax({
                url: 'login_process.php',
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (resp) {
                if (resp.success) {
                    window.location.href = resp.redirect || 'index.php';
                } else {
                    $err.removeClass('d-none').text(resp.message);
                    $btn.prop('disabled', false).text('Login');
                }
            }).fail(function () {
                $err.removeClass('d-none').text('Something went wrong. Please try again.');
                $btn.prop('disabled', false).text('Login');
            });
        });
    });
    </script>
</body>
</html>
