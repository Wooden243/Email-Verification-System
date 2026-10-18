<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\SignedUrl;

final class ValidateSignature
{
    public function __construct(private readonly SignedUrl $signer) {}

    /** @param array<string,mixed> $query */
    public function passes(array $query): bool
    {
        return $this->signer->validSignature($query);
    }
}
