<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$recipient = 'saleshead@gondalinfra.com';
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/enquiries.log';

function logEntry(string $level, string $msg): void {
    global $logFile;
    $line = '[' . date('c') . '] [' . $level . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    @error_log('Hero Industrial Park enquiry [' . $level . ']: ' . $msg);
}

function back(string $status): never {
    header('Location: index.html?' . $status . '=1#enquire');
    exit;
}

function sendViaMail(string $to, string $subject, string $body, string $from, string $fromName, ?string $replyTo, ?string $replyName): bool {
    $eol = "\r\n";
    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'From: ' . mb_encode_mimeheader($fromName) . ' <' . $from . '>';
    $headers[] = 'Reply-To: ' . ($replyTo ? mb_encode_mimeheader($replyName ?? '') . ' <' . $replyTo . '>' : $from);
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $headers[] = 'Return-Path: ' . $from;

    $safeSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $safeSubject, $body, implode($eol, $headers), '-f ' . $from);
}

function sendViaSmtp(string $to, string $subject, string $body, string $from, string $fromName, ?string $replyTo, ?string $replyName): ?bool {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }
    $host     = (string)getenv('SMTP_HOST');
    $user     = (string)getenv('SMTP_USERNAME');
    $password = (string)getenv('SMTP_PASSWORD');
    if ($host === '' || $user === '' || $password === '') {
        return null;
    }
    require_once $autoload;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(
            (string)(getenv('SMTP_FROM_EMAIL') ?: $from),
            (string)(getenv('SMTP_FROM_NAME')  ?: $fromName)
        );
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Honeypot: if filled, pretend success
if (!empty($_POST['website'] ?? '')) {
    logEntry('HONEYPOT', 'Spam detected (honeypot filled)');
    back('sent');
}

// Basic rate-limit per session (max 3 per 10 min)
$now   = time();
$key   = 'enquiry_attempts';
$window = 600;
$_SESSION[$key] = array_values(array_filter(
    $_SESSION[$key] ?? [],
    static fn($t) => ($now - $t) < $window
));
if (count($_SESSION[$key]) >= 3) {
    logEntry('RATE_LIMIT', 'Too many attempts from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    back('error');
}
$_SESSION[$key][] = $now;

function clean(string $v, int $maxLen = 10_000): string {
    $v = trim($v);
    if (mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    // Strip control chars except \r \n \t
    return (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
}

$name     = clean((string)($_POST['name']     ?? ''), 100);
$phone    = clean((string)($_POST['phone']    ?? ''), 20);
$email    = clean((string)($_POST['email']    ?? ''), 150);
$interest = clean((string)($_POST['interest'] ?? 'General project information'), 100);
$message  = clean((string)($_POST['message']  ?? ''), 1500);

if (
    $name === '' ||
    $phone === '' ||
    !preg_match('/^[+0-9()\-\s]{8,20}$/', $phone) ||
    ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL)))
) {
    logEntry('VALIDATION_FAIL', 'name=' . $name . ' phone=' . $phone);
    back('error');
}

$fromAddress = (string)(getenv('SMTP_FROM_EMAIL') ?: 'no-reply@gondalinfra.com');
$fromName    = (string)(getenv('SMTP_FROM_NAME')  ?: 'Hero Industrial Park – 3 Enquiries');

$subject = 'New Hero Industrial Park – 3 Enquiry';
$bodyLines = [
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
    'Submitted: '  . date('c'),
    'IP Address: ' . ($_SERVER['REMOTE_ADDR']     ?? ''),
    'Referer: '    . ($_SERVER['HTTP_REFERER']     ?? ''),
    'User Agent: ' . ($_SERVER['HTTP_USER_AGENT']  ?? ''),
];
$body = implode("\n", $bodyLines);

logEntry('SUBMITTED', 'name=' . $name . ' phone=' . $phone . ' email=' . $email . ' interest="' . $interest . '"');

$sent = false;

// Try SMTP first (if configured)
$smtpResult = sendViaSmtp($recipient, $subject, $body, $fromAddress, $fromName,
    $email !== '' ? $email : null,
    $email !== '' ? $name  : null
);
if ($smtpResult === true) {
    $sent = true;
    logEntry('SENT_SMTP', 'Delivered via SMTP to ' . $recipient);
} elseif ($smtpResult === false) {
    logEntry('FALLBACK', 'SMTP failed, falling back to native mail()');
}

// Native mail() fallback (most hosts support this out of the box)
if (!$sent) {
    $sent = sendViaMail($recipient, $subject, $body, $fromAddress, $fromName,
        $email !== '' ? $email : null,
        $email !== '' ? $name  : null
    );
    if ($sent) {
        logEntry('SENT_MAIL', 'Delivered via mail() to ' . $recipient);
    } else {
        logEntry('SEND_FAIL', 'Both SMTP and mail() failed.');
    }
}

// Always write a plaintext backup to disk (never lose an enquiry)
$backupDir = $logDir . '/submissions';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
$backupFile = $backupDir . '/' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.txt';
@file_put_contents($backupFile, $body . "\n\nStatus: " . ($sent ? 'SENT' : 'NOT_SENT') . "\n");

back($sent ? 'sent' : 'error');
