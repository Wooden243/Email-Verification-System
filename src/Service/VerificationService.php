<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\Middleware\ValidateExpiration;
use App\Http\Middleware\ValidateSignature;
use App\Mail\Mailer;
use App\Persistence\UserRepository;
use App\Security\SignedUrl;

final class VerificationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly SignedUrl $urls
    ) {}

    public function sendVerificationEmail(int $id, int $now): bool
    {
        $user = $this->users->findById($id);
        if ($user === null || (int) $user['email_verified'] === 1) {
            return false;
        }
        if (!$this->users->claimSendSlot($id, $now, 60)) {
            return false;
        }
        $url = $this->urls->make($id, (string) $user['email'], $now);
        $this->mailer->sendVerificationEmail((string) $user['email'], $url);
        return true;
    }

    /** @param array<string,mixed> $query Returns one of: invalid, expired, mismatch, verified. */
    public function verify(array $query, int $now): string
    {
        if (!(new ValidateSignature($this->urls))->passes($query)) {
            return 'invalid';
        }
        if (!(new ValidateExpiration())->passes($query, $now)) {
            return 'expired';
        }
        return $this->users->markVerifiedIfEmailMatches((int) $query['id'], $query['user'])
            ? 'verified' : 'mismatch';
    }
}
