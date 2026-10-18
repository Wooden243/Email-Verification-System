<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config;

final class MailerFactory
{
    public static function make(string $root): Mailer
    {
        $transport = Config::get('MAIL_TRANSPORT', 'log');
        if ($transport === 'smtp') {
            return new PhpMailerAdapter();
        }
        if ($transport === 'log' && Config::get('APP_ENV', 'local') !== 'production') {
            return new LogMailer($root . '/storage/dev-mails.log');
        }
        throw new \RuntimeException('Invalid mail transport for this environment.');
    }
}
