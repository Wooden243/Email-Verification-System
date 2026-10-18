<?php
declare(strict_types=1);

namespace App\Mail;

interface Mailer
{
    public function sendVerificationEmail(string $email, string $url): void;
}
