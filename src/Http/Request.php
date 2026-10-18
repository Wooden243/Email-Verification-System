<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $form */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $form = []
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', is_string($path) ? $path : '/', $_GET, $_POST);
    }
}
