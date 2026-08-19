<?php
/**
 * Portfolio contact form endpoint.
 */

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$config = require __DIR__ . '/config.php';

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function logError(string $message): void
{
    global $config;
    $logFile = $config['logging']['file'];
    $directory = dirname($logFile);
    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND | LOCK_EX);
}

function cleanText(?string $value): string
{
    $value = trim((string) $value);
    return strip_tags(str_replace(["\r", "\n", "\0"], '', $value));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Only POST requests are allowed.']);
}

$name = cleanText($_POST['name'] ?? '');
$phone = cleanText($_POST['phone'] ?? '');
$message = trim(strip_tags((string) ($_POST['message'] ?? '')));

$errors = [];
if ($name === '') {
    $errors[] = 'Full name is required.';
}
if ($phone === '') {
    $errors[] = 'Mobile number is required.';
}
if ($message === '') {
    $errors[] = 'Message is required.';
}
if (strlen($name) > 120 || strlen($phone) > 40 || strlen($message) > 5000) {
    $errors[] = 'One or more fields are too long.';
}
if ($errors) {
    jsonResponse(422, ['success' => false, 'message' => 'Please complete the form.', 'errors' => $errors]);
}

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->Port = $config['smtp']['port'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mail->addAddress($config['smtp']['recipient'], 'Sanjay Vaishya');
    $mail->addReplyTo($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mail->Subject = 'New portfolio contact request from ' . $name;
    $mail->isHTML(true);
    $mail->Body = <<<HTML
<div style="font-family:Arial,sans-serif;line-height:1.6;color:#17213a;max-width:640px;">
  <h2 style="color:#1d4ed8;">New Contact Request</h2>
  <p><strong>Full Name:</strong> {$safeName}</p>
  <p><strong>Mobile Number:</strong> {$safePhone}</p>
  <p><strong>Message:</strong><br>{$safeMessage}</p>
</div>
HTML;
    $mail->AltBody = "New contact request\n\nFull Name: {$name}\nMobile Number: {$phone}\nMessage:\n{$message}";
    $mail->send();
    jsonResponse(200, ['success' => true, 'message' => 'Your request was sent successfully.']);
} catch (Exception $exception) {
    logError('SMTP send failed: ' . $exception->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Unable to send your request right now.']);
}