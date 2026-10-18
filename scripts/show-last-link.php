<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';
if (App\Config::get('APP_ENV', 'local') === 'production') {
    fwrite(STDERR, "Unavailable in production.\n");
    exit(1);
}
$path = $root . '/storage/dev-mails.log';
$lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$last = $lines ? json_decode(end($lines), true) : null;
if (!is_array($last) || !isset($last['verification_url'])) {
    fwrite(STDERR, "No development emails have been logged.\n");
    exit(1);
}
echo $last['verification_url'] . "\n";
