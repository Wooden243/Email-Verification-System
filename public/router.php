<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$target = __DIR__ . $path;
if ($path !== '/' && is_file($target) && !str_ends_with($target, '.php')) {
    return false;
}
require __DIR__ . '/index.php';
