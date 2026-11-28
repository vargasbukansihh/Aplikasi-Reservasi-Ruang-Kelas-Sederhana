<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

function getPasswordResetMailConfiguration(): array
{
    $configPath = __DIR__ . '/smtp_config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException(
            'SMTP configuration is missing. Copy smtp_config.example.php to smtp_config.php and fill it in.'
        );
    }

    $configuration = require $configPath;
    if (!is_array($configuration)) {
        throw new RuntimeException('SMTP configuration must return an array.');
    }

    $host = $configuration['host'] ?? null;
    $port = $configuration['port'] ?? null;
    $username = $configuration['username'] ?? null;
    $password = $configuration['password'] ?? null;
    $encryption = $configuration['encryption'] ?? null;
    $fromAddress = $configuration['from_email'] ?? null;
    $fromName = $configuration['from_name'] ?? null;
    $baseUrl = $configuration['base_url'] ?? null;
    $parts = is_string($baseUrl) ? parse_url(rtrim($baseUrl, '/')) : false;

    if (
        !is_string($host)
        || preg_match('/\A[a-z0-9.-]+\z/i', $host) !== 1
        || !is_int($port)
        || $port < 1
        || $port > 65535
        || !is_string($username)
        || trim($username) === ''
        || !is_string($password)
        || $password === ''
        || !in_array($encryption, ['tls', 'ssl'], true)
        || !is_string($fromAddress)
        || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false
        || !is_string($fromName)
        || trim($fromName) === ''
        || preg_match('/[\r\n]/', $fromName) === 1
        || !is_string($baseUrl)
        || filter_var(rtrim($baseUrl, '/'), FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        throw new RuntimeException('SMTP configuration is incomplete or invalid.');
    }

    if (
        strtolower($parts['scheme']) !== 'https'
        && !in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true)
    ) {
        throw new RuntimeException('The application base URL must use HTTPS outside localhost.');
    }

    $configuration['base_url'] = rtrim($baseUrl, '/');

    return $configuration;
}

function sendPasswordResetEmail(string $recipient, string $token, array $configuration): bool
{
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $configuration['host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $configuration['username'];
    $mailer->Password = $configuration['password'];
    $mailer->SMTPSecure = $configuration['encryption'] === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = $configuration['port'];
    $mailer->Timeout = 20;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($configuration['from_email'], $configuration['from_name']);
    $mailer->addAddress($recipient);
    $mailer->isHTML(false);
    $mailer->Subject = 'Reset password - Reservasi Kelas';
    $mailer->Body = "Kami menerima permintaan untuk mengatur ulang password akun Anda.\n\n"
        . "Buka tautan berikut dalam 30 menit:\n"
        . $configuration['base_url']
        . '/reset_password.php?token=' . rawurlencode($token) . "\n\n"
        . "Jika Anda tidak meminta pengaturan ulang password, abaikan email ini.";

    try {
        return $mailer->send();
    } catch (PHPMailerException $exception) {
        throw new RuntimeException('SMTP email delivery failed: ' . $exception->getMessage(), 0, $exception);
    }
}
