<?php
declare(strict_types=1);

namespace App\Security;

final class SignedUrl
{
    public function __construct(private readonly string $key, private readonly string $baseUrl)
    {
        if ($key === '') {
            throw new \InvalidArgumentException('A signing key is required.');
        }
        if (!preg_match('~^https?://[^/?#]+$~D', rtrim($baseUrl, '/'))) {
            throw new \InvalidArgumentException('APP_URL must be an HTTP(S) origin without a path.');
        }
    }

    public static function emailDigest(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** @return array{id:string,user:string,expiration:string,signature:string} */
    public function parameters(int $id, string $email, int $now): array
    {
        $params = [
            'id' => (string) $id,
            'user' => self::emailDigest($email),
            'expiration' => (string) ($now + 1800),
        ];
        $params['signature'] = $this->signature($params);
        return $params;
    }

    public function make(int $id, string $email, int $now): string
    {
        return rtrim($this->baseUrl, '/') . '/verify/email?'
            . http_build_query($this->parameters($id, $email, $now), '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $query */
    public function validSignature(array $query): bool
    {
        if (count($query) !== 4) {
            return false;
        }
        foreach (['id', 'user', 'expiration', 'signature'] as $name) {
            if (!isset($query[$name]) || !is_string($query[$name])) {
                return false;
            }
        }
        if (!preg_match('/^[1-9][0-9]*$/D', $query['id'])
            || !preg_match('/^[0-9]{10,11}$/D', $query['expiration'])
            || !preg_match('/^[a-f0-9]{64}$/D', $query['user'])
            || !preg_match('/^[a-f0-9]{64}$/D', $query['signature'])) {
            return false;
        }
        $params = ['id' => $query['id'], 'user' => $query['user'], 'expiration' => $query['expiration']];
        return hash_equals($this->signature($params), $query['signature']);
    }

    /** @param array{id:string,user:string,expiration:string} $params */
    private function signature(array $params): string
    {
        $message = '/verify/email?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return hash_hmac('sha256', $message, $this->key);
    }
}
