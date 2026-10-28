<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
admin_require_login();

$page_title = 'Message Details';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$message = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$message) {
    admin_flash_set('error', 'Message not found.');
    header('Location: messages.php');
    exit;
}

// Viewing a "new" message marks it read - no separate action needed for that step.
if ($message['status'] === 'new') {
    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message['status'] = 'read';
}

$csrf = admin_csrf_token();
include __DIR__ . '/header.php';
?>

<a href="messages.php" class="btn btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Messages</a>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="mb-0"><?php echo admin_esc($message['subject']) ?></h4>
                    <span class="badge <?php echo ['new' => 'badge-danger', 'read' => 'badge-secondary', 'replied' => 'badge-success'][$message['status']] ?? 'badge-light' ?>"><?php echo ucfirst($message['status']) ?></span>
                </div>
                <table class="table table-sm">
                    <tr><th style="width:25%">From</th><td><?php echo admin_esc($message['name']) ?> &lt;<?php echo admin_esc($message['email']) ?>&gt;</td></tr>
                    <tr><th>Received</th><td><?php echo date('l, F j, Y g:i A', strtotime($message['created_at'])) ?></td></tr>
                </table>
                <hr>
                <p style="white-space: pre-wrap;"><?php echo admin_esc($message['message']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5>Actions</h5>
                <a href="mailto:<?php echo admin_esc($message['email']) ?>?subject=<?php echo urlencode('Re: ' . $message['subject']) ?>" class="btn btn-primary btn-block mb-2"><i class="fas fa-reply"></i> Reply by Email</a>
                <?php if ($message['status'] !== 'replied'): ?>
                <button type="button" class="btn btn-outline-success btn-block mb-2" id="btnMarkReplied"><i class="fas fa-check"></i> Mark as Replied</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-danger btn-block" id="btnDeleteMessage"><i class="fas fa-trash"></i> Delete Message</button>
            </div>
        </div>
    </div>
</div>

<form id="delete_message_form" method="POST" action="message_delete.php" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo admin_esc($csrf) ?>">
    <input type="hidden" name="id" value="<?php echo (int)$id ?>">
</form>

<script>
$('#btnMarkReplied').on('click', function () {
    $.ajax({
        url: 'message_mark_replied.php',
        method: 'POST',
        data: { id: <?php echo (int)$id ?>, csrf_token: <?php echo json_encode($csrf) ?> },
        dataType: 'json'
    }).done(function (resp) {
        if (resp.success) { location.reload(); } else { Swal.fire({icon:'error', title:'Failed', text: resp.message}); }
    }).fail(function () {
        Swal.fire({icon:'error', title:'Error', text:'Something went wrong. Please try again.'});
    });
});

$('#btnDeleteMessage').on('click', function () {
    adminConfirm('Delete this message? This cannot be undone.', function () {
        $('#delete_message_form').submit();
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
