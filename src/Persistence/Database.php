<?php
declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException('pdo_sqlite is required. Install php-sqlite3 for your PHP version.');
        }
        if (!is_file($path)) {
            throw new \RuntimeException('Database missing; run php scripts/setup.php.');
        }
        $db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
}
