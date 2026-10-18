<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';

use App\App;
use App\Config;
use App\Http\Request;
use App\Mail\MailerFactory;
use App\Persistence\Database;
use App\Persistence\SqliteUserRepository;
use App\Security\SignedUrl;
use App\Service\VerificationService;

$production = Config::get('APP_ENV', 'local') === 'production';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if ($production && !str_starts_with(Config::get('APP_URL'), 'https://')) {
    http_response_code(500);
    exit('Production APP_URL must use HTTPS.');
}
if ($production && !$https) {
    http_response_code(403);
    exit('HTTPS is required in production.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params(['httponly' => true, 'secure' => $production, 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

try {
    $db = Database::connect($root . '/storage/app.sqlite');
    $users = new SqliteUserRepository($db);
    $urls = new SignedUrl(Config::signingKey(), Config::get('APP_URL', 'http://127.0.0.1:8000'));
    $verification = new VerificationService($users, MailerFactory::make($root), $urls);
    $app = new App($users, $verification, static fn (): int => time());
    $app->handle(Request::fromGlobals())->send();
} catch (Throwable $e) {
    error_log('App error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Server error. Check server logs and setup.';
}
