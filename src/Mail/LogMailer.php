<?php
declare(strict_types=1);

namespace App\Mail;

final class LogMailer implements Mailer
{
    public function __construct(private readonly string $path) {}

    public function sendVerificationEmail(string $email, string $url): void
    {
        $line = json_encode(['timestamp' => gmdate(DATE_ATOM), 'to' => $email, 'verification_url' => $url], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write development mail log.');
        }
        @chmod($this->path, 0600);
    }
}
