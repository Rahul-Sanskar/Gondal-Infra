<?php
/**
 * Hero Industrial Park – 3 — Enquiry Handler
 * Target hosting: Hostinger (shared / cloud / VPS with PHP + SMTP).
 *
 * HOW TO USE ON HOSTINGER:
 *   1. Create a business email in Hostinger hPanel → Emails (e.g. sales@gondalinfra.com).
 *   2. From hPanel → Email Accounts → Connection Settings, copy:
 *        SMTP_HOST, SMTP_PORT, SMTP_USERNAME (your full email), SMTP_PASSWORD.
 *   3. Paste those values into the CONFIG block below.
 *   4. Recipient is saleshead@gondalinfra.com by default — change below if needed.
 *
 * FALLBACK BEHAVIOUR:
 *   - If SMTP is fully configured  → sends via PHPMailer (SMTP).
 *   - If vendor/autoload.php missing → uses bundled mail() + writes to logs/.
 *   - If mail() is also disabled     → enquiry still saved in logs/submissions/.
 *
 * SUBMISSION SAFETY: every enquiry is saved on disk in logs/submissions/*.txt
 *                    regardless of whether email delivery succeeded.
 */

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------------------------------------------------------------------------- *
 *  CONFIG — edit these values for Hostinger / Gondal Infra
 * -------------------------------------------------------------------------- */
$recipient         = 'saleshead@gondalinfra.com'; // Who receives enquiries

// Hostinger Titan Mail / Hostinger Email SMTP — fill in from hPanel → Emails → Connection Settings
$smtp = [
    'host'     => getenv('SMTP_HOST')     ?: '', // e.g. 'smtp.titan.email' (Titan) or 'smtp.hostinger.com'
    'port'     => (int)(getenv('SMTP_PORT') ?: 587),   // 587 for TLS
    'username' => getenv('SMTP_USERNAME') ?: '', // e.g. 'sales@gondalinfra.com'
    'password' => getenv('SMTP_PASSWORD') ?: '', // email password (Titan: app password if MFA on)
    'secure'   => 'tls',                      // or 'ssl' with port 465
];

// Used as the "From:" on sent emails. Ideally matches SMTP_USERNAME to avoid spam folders.
$fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($smtp['username'] !== '' ? $smtp['username'] : 'no-reply@gondalinfra.com');
$fromName  = getenv('SMTP_FROM_NAME')  ?: 'Hero Industrial Park – 3 Enquiries';

/* -------------------------------------------------------------------------- *
 *  Runtime helpers
 * -------------------------------------------------------------------------- */
$logDir       = __DIR__ . '/logs';
$submissionDir = $logDir . '/submissions';
foreach ([$logDir, $submissionDir] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
}
$logFile = $logDir . '/enquiries.log';

function logEntry(string $level, string $msg): void {
    global $logFile;
    $line = '[' . date('c') . '] [' . $level . '] ' . preg_replace('/\s+/', ' ', trim($msg)) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    @error_log('[Enquiry ' . $level . '] ' . $msg);
}

function back(string $status): never {
    header('Location: index.html?' . $status . '=1#enquire');
    exit;
}

/* -------------------------------------------------------------------------- *
 *  Transport 1 — PHPMailer SMTP (best deliverability on Hostinger)
 * -------------------------------------------------------------------------- */
function smtpSend(
    array $smtp,
    string $to, string $subject, string $body,
    string $fromEmail, string $fromName,
    ?string $replyTo, ?string $replyName
): ?bool {
    if ($smtp['host'] === '' || $smtp['username'] === '' || $smtp['password'] === '') {
        return null; // SMTP not configured — caller will fall back
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        logEntry('SMTP_SKIP', 'vendor/autoload.php missing — run `composer install` in the project root to enable SMTP');
        return null;
    }
    require_once $autoload;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->Port       = $smtp['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->CharSet    = 'UTF-8';
        if ($smtp['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        if ($replyTo) {
            $mail->addReplyTo($replyTo, (string)($replyName ?? ''));
        }
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $body;
        return $mail->send();
    } catch (Throwable $e) {
        logEntry('SMTP_FAIL', $e->getMessage());
        return false;
    }
}

/* -------------------------------------------------------------------------- *
 *  Transport 2 — native PHP mail()  (Hostinger fallback; sometimes blocked)
 * -------------------------------------------------------------------------- */
function nativeMailSend(
    string $to, string $subject, string $body,
    string $fromEmail, string $fromName,
    ?string $replyTo, ?string $replyName
): bool {
    $eol = "\r\n";
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . ($replyTo ? mb_encode_mimeheader($replyName ?? '') . ' <' . $replyTo . '>' : $fromEmail),
        'X-Mailer: PHP/' . phpversion(),
    ];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, implode($eol, $headers), '-f ' . $fromEmail);
    if (!$ok) {
        logEntry('MAIL_FAIL', 'PHP mail() returned false. Check Hostinger outgoing mail limits or switch to SMTP.');
    }
    return $ok;
}

/* -------------------------------------------------------------------------- *
 *  Request guard
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Honeypot — bots usually fill hidden fields; humans don't.
if (!empty($_POST['website'] ?? '')) {
    logEntry('HONEYPOT', 'Rejected (honeypot filled)');
    back('sent');
}

// Light per-session rate-limit: 3 submissions / 10 minutes max.
$now = time();
$window = 600;
$_SESSION['enquiry_attempts'] = array_values(array_filter(
    $_SESSION['enquiry_attempts'] ?? [],
    static fn($t) => ($now - $t) < $window
));
if (count($_SESSION['enquiry_attempts']) >= 3) {
    logEntry('RATE_LIMIT', 'Rejected from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    back('error');
}
$_SESSION['enquiry_attempts'][] = $now;

/* -------------------------------------------------------------------------- *
 *  Input sanitisation + validation
 * -------------------------------------------------------------------------- */
function clean(string $v, int $maxLen = 10_000): string {
    $v = trim($v);
    if (mb_strlen($v) > $maxLen) { $v = mb_substr($v, 0, $maxLen); }
    return (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
}

$name     = clean((string)($_POST['name']     ?? ''), 100);
$phone    = clean((string)($_POST['phone']    ?? ''), 20);
$email    = clean((string)($_POST['email']    ?? ''), 150);
$interest = clean((string)($_POST['interest'] ?? 'General project information'), 100);
$message  = clean((string)($_POST['message']  ?? ''), 1500);

if (
    $name === '' || $phone === '' ||
    !preg_match('/^[+0-9()\-\s]{8,20}$/', $phone) ||
    ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
) {
    logEntry('VALIDATION_FAIL', 'name="' . $name . '" phone="' . $phone . '"');
    back('error');
}

/* -------------------------------------------------------------------------- *
 *  Build email body
 * -------------------------------------------------------------------------- */
$subject = 'New Hero Industrial Park – 3 Enquiry';
$body = implode("\n", [
    'New enquiry received from the Hero Industrial Park – 3 landing page.',
    '',
    'Name: '     . $name,
    'Phone: '    . $phone,
    'Email: '    . ($email !== '' ? $email : 'Not provided'),
    'Interest: ' . $interest,
    '',
    'Message:',
    ($message !== '' ? $message : 'Not provided'),
    '',
    'Submitted:  ' . date('c'),
    'IP Address: ' . ($_SERVER['REMOTE_ADDR']     ?? ''),
    'Referer:    ' . ($_SERVER['HTTP_REFERER']     ?? ''),
    'User Agent: ' . ($_SERVER['HTTP_USER_AGENT']  ?? ''),
]);

logEntry('SUBMITTED', 'name="' . $name . '" phone="' . $phone . '" email="' . $email . '" interest="' . $interest . '"');

/* -------------------------------------------------------------------------- *
 *  Deliver — try SMTP first, then native mail(). Never throw to the visitor.
 * -------------------------------------------------------------------------- */
$sent  = false;
$tried = [];

try {
    $smtpResult = smtpSend($smtp, $recipient, $subject, $body, $fromEmail, $fromName,
        $email !== '' ? $email : null,
        $email !== '' ? $name  : null
    );
    if ($smtpResult === true) {
        $sent = true;
        $tried[] = 'SMTP';
        logEntry('SENT_SMTP', 'to ' . $recipient . ' via ' . $smtp['host'] . ':' . $smtp['port']);
    } elseif ($smtpResult === false) {
        $tried[] = 'SMTP(failed)';
        logEntry('FALLBACK', 'SMTP failed — trying PHP mail()');
    } else {
        $tried[] = 'SMTP(not_configured)';
    }
} catch (Throwable $e) {
    logEntry('SMTP_ERROR', $e->getMessage());
}

if (!$sent) {
    if (nativeMailSend($recipient, $subject, $body, $fromEmail, $fromName,
        $email !== '' ? $email : null,
        $email !== '' ? $name  : null
    )) {
        $sent  = true;
        $tried[] = 'mail()';
        logEntry('SENT_MAIL', 'to ' . $recipient);
    } else {
        $tried[] = 'mail()(failed)';
        logEntry('SEND_FAIL', 'No transport delivered via methods: ' . implode(', ', $tried));
    }
}

/* -------------------------------------------------------------------------- *
 *  On-disk backup — enquiry is never lost even if all emails fail.
 * -------------------------------------------------------------------------- */
try {
    $safeName = preg_replace('/[^A-Za-z0-9]/', '', $name);
    $id = substr(bin2hex(random_bytes(4)), 0, 8);
    $backupFile = $submissionDir . '/' . date('Ymd-His') . '-' . $id . '-' . substr($safeName, 0, 24) . '.txt';
    $backup = $body . "\n\n---\nDelivery status: " . ($sent ? 'SENT' : 'NOT_SENT')
        . "\nTransport(s) tried: " . implode(', ', $tried) . "\n";
    @file_put_contents($backupFile, $backup, LOCK_EX);
} catch (Throwable $e) {
    logEntry('BACKUP_FAIL', $e->getMessage());
}

back($sent ? 'sent' : 'error');
