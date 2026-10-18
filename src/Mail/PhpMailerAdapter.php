<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config;
use PHPMailer\PHPMailer\PHPMailer;

final class PhpMailerAdapter implements Mailer
{
    public function sendVerificationEmail(string $email, string $url): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer missing; run composer install.');
        }
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = Config::get('SMTP_HOST');
        $mailer->Port = (int) Config::get('SMTP_PORT', '587');
        $mailer->SMTPAuth = true;
        $mailer->Username = Config::get('SMTP_USERNAME');
        $mailer->Password = Config::get('SMTP_PASSWORD');
        $mailer->SMTPSecure = Config::get('SMTP_ENCRYPTION', 'tls');
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(Config::get('MAIL_FROM_ADDRESS'), Config::get('MAIL_FROM_NAME', 'Email Verification System'));
        $mailer->addAddress($email);
        $mailer->Subject = 'Verify your email address';
        $mailer->Body = "Open the following link within 30 minutes to verify your email address:\n\n" . $url . "\n\nIf you did not register, ignore this email.";
        $mailer->send();
    }
}
