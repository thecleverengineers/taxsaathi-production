<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('mail_config')) {
    function mail_config(): array
    {
        static $config;

        if ($config === null) {
            $path = dirname(__DIR__, 2) . '/config/mail.php';
            $config = is_file($path) ? (array) require $path : [];
        }

        return $config;
    }
}

if (!function_exists('send_smtp_mail')) {
    function send_smtp_mail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        $config = mail_config();

        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run composer require phpmailer/phpmailer');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = (string) ($config['host'] ?? '');
            $mail->Port       = (int) ($config['port'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) ($config['username'] ?? '');
            $mail->Password   = (string) ($config['password'] ?? '');

            $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom(
                (string) ($config['from_email'] ?? ''),
                (string) ($config['from_name'] ?? 'Website')
            );
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            return $mail->send();
        } catch (Exception $e) {
            error_log('SMTP Mail Error: ' . $e->getMessage());
            return false;
        }
    }
}