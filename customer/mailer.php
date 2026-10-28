<?php
/**
 * customer/mailer.php
 *
 * Minimal mail sender for the password-reset flow.
 *
 * -----------------------------------------------------------------
 * HOW TO CONFIGURE REAL SMTP LATER
 * -----------------------------------------------------------------
 * This currently tries PHP's built-in mail(), which needs a working
 * local mail transport (XAMPP does NOT set one up by default) or an
 * SMTP relay configured in php.ini. That's fine for building the
 * feature now, but you'll want real SMTP before going live.
 *
 * To wire up real email later:
 *   1. Install PHPMailer, e.g.: composer require phpmailer/phpmailer
 *   2. Fill in the SMTP_* constants below.
 *   3. Uncomment the PHPMailer block inside send_customer_email().
 *
 * Until SMTP is configured, every reset link is ALSO written to
 * customer/logs/password_resets.log so the forgot-password flow can
 * be tested end-to-end on localhost without a real mailbox.
 * Delete or secure that logs/ folder before deploying to a public
 * server — it is local developer convenience only, never exposed to
 * the browser or API response.
 * -----------------------------------------------------------------
 */

// ---- Fill these in when real SMTP is ready ----
define('SMTP_HOST', '');       // e.g. smtp.gmail.com — leave blank to skip SMTP
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'no-reply@example.com');
define('SMTP_FROM_NAME', 'Hotel Management System');

/**
 * Sends a password-reset (or other transactional) email.
 * Returns true/false, but callers should NOT rely on this for
 * security decisions — see log_password_reset_link() below.
 */
function send_customer_email($to_email, $subject, $html_body) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";

    if (SMTP_HOST !== '') {
        // TODO: once PHPMailer is installed via composer, replace this
        // block with real SMTP sending, e.g.:
        //
        // require __DIR__ . '/../vendor/autoload.php';
        // $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // $mail->isSMTP();
        // $mail->Host       = SMTP_HOST;
        // $mail->SMTPAuth   = true;
        // $mail->Username   = SMTP_USERNAME;
        // $mail->Password   = SMTP_PASSWORD;
        // $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = SMTP_PORT;
        // $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        // $mail->addAddress($to_email);
        // $mail->isHTML(true);
        // $mail->Subject = $subject;
        // $mail->Body    = $html_body;
        // return $mail->send();
        return false;
    }

    return @mail($to_email, $subject, $html_body, $headers);
}

/**
 * Always logs the reset link locally so the flow is testable on
 * XAMPP without any email server configured. This is written
 * server-side only — it is never returned in an HTTP response.
 */
function log_password_reset_link($email, $reset_link) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $email . ' -> ' . $reset_link . PHP_EOL;
    @file_put_contents($dir . '/password_resets.log', $line, FILE_APPEND);
}
