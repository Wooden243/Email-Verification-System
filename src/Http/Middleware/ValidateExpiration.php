<?php
declare(strict_types=1);

namespace App\Http\Middleware;

final class ValidateExpiration
{
    /** @param array<string,mixed> $query */
    public function passes(array $query, int $now): bool
    {
        return isset($query['expiration'])
            && is_string($query['expiration'])
            && ctype_digit($query['expiration'])
            && (int) $query['expiration'] > $now;
    }
}
