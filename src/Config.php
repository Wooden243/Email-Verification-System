<?php
declare(strict_types=1);

namespace App;

final class Config
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        self::$values = [];
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                self::$values[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        return $env !== false ? $env : (self::$values[$key] ?? $default);
    }

    public static function signingKey(): string
    {
        $key = self::get('APP_KEY');
        if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
            throw new \RuntimeException('APP_KEY must be 64 hexadecimal characters; run php scripts/setup.php.');
        }
        return hex2bin($key);
    }
}
