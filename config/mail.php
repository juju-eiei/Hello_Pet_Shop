<?php
require_once __DIR__ . '/config.php';

$smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? 'yingaung651@gmail.com');
$smtpPass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? 'bmhbicoioxjzrrre');
$fromEmail = getenv('FROM_EMAIL') ?: ($_ENV['FROM_EMAIL'] ?? 'yingaung651@gmail.com');
$fromName = getenv('FROM_NAME') ?: ($_ENV['FROM_NAME'] ?? 'Hello Pet Shop');

return [
    'smtp_host'   => getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'),
    'smtp_port'   => (int)(getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587)),
    'smtp_auth'   => true,
    'smtp_secure' => getenv('SMTP_SECURE') ?: ($_ENV['SMTP_SECURE'] ?? 'tls'),
    'smtp_user'   => $smtpUser,
    'smtp_pass'   => $smtpPass,
    'from_email'  => $fromEmail,
    'from_name'   => $fromName
];
?>
