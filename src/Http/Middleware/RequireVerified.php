<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class RequireVerified
{
    /** @param array<string,mixed>|null $user */
    public function check(?array $user): ?Response
    {
        if ($user === null) {
            return Response::redirect('/login');
        }
        return (int) $user['email_verified'] === 1 ? null : Response::redirect('/verify/resend');
    }
}
