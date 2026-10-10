<?php
declare(strict_types=1);
session_start();
// Configure SMTP in environment variables on your hosting panel. Do not put SMTP credentials in public files.
$recipient = 'saleshead@gondalinfra.com';
function back(string $status): never { header('Location: index.html?' . $status . '=1#enquire'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!empty($_POST['website'] ?? '')) { back('sent'); } // honeypot
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$interest = trim((string)($_POST['interest'] ?? 'General project information'));
$message = trim((string)($_POST['message'] ?? ''));
if ($name === '' || mb_strlen($name) > 100 || $phone === '' || strlen($phone) > 20 || !preg_match('/^[+0-9()\-\s]{8,20}$/', $phone) || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) || mb_strlen($message) > 1500) { back('error'); }
// PHPMailer is installed with: composer install (see composer.json and README.txt).
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) { error_log('Hero Industrial Park enquiry: PHPMailer dependencies are missing.'); back('error'); }
require $autoload;
try {
  $mail = new PHPMailer\PHPMailer\PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = getenv('SMTP_USERNAME') ?: '';
  $mail->Password = getenv('SMTP_PASSWORD') ?: '';
  $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
  $mail->CharSet = 'UTF-8';
  $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: $mail->Username, getenv('SMTP_FROM_NAME') ?: 'Hero Industrial Park – 3 Enquiries');
  $mail->addAddress($recipient);
  if ($email !== '') { $mail->addReplyTo($email, $name); }
  $mail->Subject = 'New Hero Industrial Park – 3 Enquiry';
  $body = "New enquiry received from the Hero Industrial Park – 3 landing page.\n\n" .
    "Name: {$name}\nPhone: {$phone}\nEmail: " . ($email !== '' ? $email : 'Not provided') . "\nInterest: {$interest}\n\nMessage:\n" . ($message !== '' ? $message : 'Not provided') . "\n\nSubmitted: " . date(DATE_ATOM);
  $mail->Body = $body;
  $mail->AltBody = $body;
  $mail->send();
  back('sent');
} catch (Throwable $e) {
  error_log('Hero Industrial Park SMTP send failed: ' . $e->getMessage());
  back('error');
}
