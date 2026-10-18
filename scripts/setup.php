<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . '/.env';
if (!is_file($envPath)) {
    $template = file_get_contents($root . '/.env.example');
    if ($template === false) {
        throw new RuntimeException('Missing .env.example');
    }
    $template = preg_replace('/^APP_KEY=$/m', 'APP_KEY=' . bin2hex(random_bytes(32)), $template);
    if (file_put_contents($envPath, $template, LOCK_EX) === false) {
        throw new RuntimeException('Unable to create .env');
    }
    @chmod($envPath, 0600);
    echo "Created .env with a random APP_KEY.\n";
} else {
    echo "Keeping existing .env and APP_KEY.\n";
}
require $root . '/src/bootstrap.php';
App\Config::signingKey();
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Missing pdo_sqlite extension. Install php-sqlite3, then rerun setup.\n");
    exit(1);
}
$path = $root . '/storage/app.sqlite';
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));
@chmod($path, 0600);
echo "Database ready: storage/app.sqlite\n";
