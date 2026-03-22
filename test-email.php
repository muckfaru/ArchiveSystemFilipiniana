<?php
/**
 * Email Diagnostic Script - TEMPORARY - DELETE AFTER USE
 */

require_once __DIR__ . '/backend/core/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/backend/core/PHPMailer/Exception.php';
require_once __DIR__ . '/backend/core/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/backend/core/PHPMailer/SMTP.php';

// Simple auth check - only allow from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied. This script can only be run from localhost.');
}

$testTo = $_GET['to'] ?? '';
$result  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['to'])) {
    $testTo = htmlspecialchars(trim($_POST['to']));
    ob_start();

    $mail = new PHPMailer(true);
    $smtpUsername = trim((string) SMTP_USERNAME);
    $smtpPassword = preg_replace('/\s+/', '', (string) SMTP_PASSWORD);
    $smtpPort = (int) SMTP_PORT;
    try {
        // Full debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='font-size:12px; background:#f0f0f0; padding:2px 6px; margin:1px 0;'>[Debug L$level] " . htmlspecialchars($str) . "</pre>";
            flush();
        };

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsername;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = $smtpPort === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($smtpUsername, SMTP_FROM_NAME);
        $mail->addAddress($testTo);

        $mail->isHTML(true);
        $mail->Subject = 'Test Email - Archive System';
        $mail->Body    = '<h2 style="color:#3A9AFF;">Test Email</h2><p>This is a test email from the Archive System. If you received this, the SMTP configuration is working correctly!</p>';
        $mail->AltBody = 'This is a test email from the Archive System. SMTP is working.';

        $mail->send();
        $result = '<div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:6px;margin-top:16px;"><strong>✅ Email sent successfully</strong> to ' . $testTo . '!</div>';
    } catch (Exception $e) {
        $result = '<div style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:6px;margin-top:16px;"><strong>❌ Email failed:</strong> ' . htmlspecialchars($mail->ErrorInfo) . '</div>';
    }
    $debugOutput = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Diagnostics</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 750px; margin: 40px auto; padding: 20px; }
  h1  { color: #3A9AFF; }
  label { display:block; margin-bottom:6px; font-weight:bold; }
  input[type=email] { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:15px; box-sizing:border-box; }
  button { margin-top:12px; padding:10px 24px; background:#3A9AFF; color:white; border:none; border-radius:6px; cursor:pointer; font-size:15px; }
  button:hover { background:#2179dd; }
  .config-table td { padding:4px 10px; font-size:14px; }
  .config-table td:first-child { font-weight:bold; color:#555; width:160px; }
  .debug-box { background:#1e1e1e; color:#d4d4d4; padding:14px; border-radius:6px; margin-top:16px; font-size:12px; overflow-x:auto; max-height:380px; overflow-y:auto; }
</style>
</head>
<body>
<h1>📧 Email Diagnostic Tool</h1>

<h3>Current SMTP Configuration</h3>
<table class="config-table">
  <tr><td>Host</td><td><?= SMTP_HOST ?></td></tr>
  <tr><td>Port</td><td><?= SMTP_PORT ?></td></tr>
  <tr><td>Username</td><td><?= SMTP_USERNAME ?></td></tr>
  <tr><td>Password</td><td><?= str_repeat('*', strlen(SMTP_PASSWORD)) ?> (<?= strlen(SMTP_PASSWORD) ?> chars)</td></tr>
  <tr><td>From Name</td><td><?= SMTP_FROM_NAME ?></td></tr>
  <tr><td>Encryption</td><td><?= SMTP_PORT == 465 ? 'SMTPS (SSL)' : 'STARTTLS' ?></td></tr>
</table>

<h3 style="margin-top:24px;">Send Test Email</h3>
<form method="POST">
  <label for="to">Send test to email address:</label>
  <input type="email" id="to" name="to" value="<?= htmlspecialchars($testTo) ?>" placeholder="youremail@example.com" required>
  <button type="submit">Send Test Email</button>
</form>

<?php if (!empty($result)): ?>
  <?= $result ?>
  <?php if (!empty($debugOutput)): ?>
    <h4 style="margin-top:20px;">SMTP Debug Output:</h4>
    <div class="debug-box"><?= $debugOutput ?></div>
  <?php endif; ?>
<?php endif; ?>

<p style="margin-top:30px; color:#999; font-size:12px;">⚠️ Delete this file after debugging: <code>test-email.php</code></p>
</body>
</html>
