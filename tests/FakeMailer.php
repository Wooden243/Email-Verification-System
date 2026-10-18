<?php
declare(strict_types=1);

namespace Tests;

use App\Mail\Mailer;

final class FakeMailer implements Mailer
{
    /** @var list<array{to:string,url:string}> */
    public array $sent = [];

    public function sendVerificationEmail(string $email, string $url): void
    {
        $this->sent[] = ['to' => $email, 'url' => $url];
    }
}
