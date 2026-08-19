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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New contact request</title>
</head>
<body style="margin:0;padding:24px 12px;background:#eef3fb;color:#17213a;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">New contact request from {$safeName}.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:680px;margin:0 auto;">
        <tr>
            <td style="padding:0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #d8e2f0;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(36,61,98,0.12);">
                    <tr>
                        <td style="padding:28px 30px;background:#101d3a;">
                            <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#a9c4ff;font-weight:700;">Sanjay Vaishya</div>
                            <div style="margin-top:8px;font-size:26px;line-height:1.2;color:#ffffff;font-weight:700;">New contact request</div>
                            <div style="margin-top:14px;display:inline-block;padding:7px 12px;border:1px solid rgba(169,196,255,0.35);border-radius:999px;color:#dce8ff;font-size:12px;font-weight:700;">READY FOR REVIEW</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <div style="margin-bottom:18px;color:#60708d;font-size:13px;line-height:1.5;">Someone submitted the contact form on your portfolio website.</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;border-spacing:0;background:#f5f8fd;border:1px solid #e1e9f4;border-radius:14px;">
                                <tr>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e1e9f4;width:34%;color:#71809d;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Full name</td>
                                    <td style="padding:16px 18px;border-bottom:1px solid #e1e9f4;color:#17213a;font-size:15px;font-weight:700;">{$safeName}</td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;color:#71809d;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Mobile number</td>
                                    <td style="padding:16px 18px;color:#17213a;font-size:15px;font-weight:700;">{$safePhone}</td>
                                </tr>
                            </table>
                            <div style="margin-top:26px;margin-bottom:10px;color:#17213a;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Message</div>
                            <div style="padding:18px 20px;background:#f8fbff;border-left:4px solid #6e9fff;border-radius:10px;color:#344563;font-size:15px;line-height:1.7;">{$safeMessage}</div>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
                                <tr>
                                    <td style="border-radius:10px;background:#1d4ed8;text-align:center;">
                                        <a href="tel:+919203251821" style="display:inline-block;padding:13px 20px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">Call Sanjay</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px;background:#f5f8fd;border-top:1px solid #e1e9f4;color:#71809d;font-size:12px;line-height:1.6;">This message was sent from the contact form on sanjayvaishya.com.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    $mail->AltBody = "New contact request\n\nFull Name: {$name}\nMobile Number: {$phone}\nMessage:\n{$message}";
    $mail->send();
    jsonResponse(200, ['success' => true, 'message' => 'Your request was sent successfully.']);
} catch (Exception $exception) {
    logError('SMTP send failed: ' . $exception->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Unable to send your request right now.']);
}