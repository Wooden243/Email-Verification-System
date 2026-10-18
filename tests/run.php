<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/FakeUserRepository.php';
require __DIR__ . '/FakeMailer.php';

use App\App;
use App\Http\Middleware\RequireVerified;
use App\Http\Request;
use App\Security\Csrf;
use App\Security\SignedUrl;
use App\Service\VerificationService;
use Tests\FakeMailer;
use Tests\FakeUserRepository;

session_start();
$_SESSION = [];
$count = 0;
$failures = [];
function check(bool $test, string $label): void {
    global $count, $failures;
    ++$count;
    if (!$test) {
        $failures[] = $label;
    }
}
function req(string $method, string $path, array $form = [], array $query = []): Request {
    return new Request($method, $path, $query, $form);
}

$now = 1792348800;
$urls = new SignedUrl('test-secret-key-for-only-local-tests', 'http://127.0.0.1:8000');
$users = new FakeUserRepository();
$mailer = new FakeMailer();
$service = new VerificationService($users, $mailer, $urls);
$id = $users->create('Hello@Example.com', password_hash('correct-password', PASSWORD_DEFAULT));
$params = $urls->parameters($id, 'hello@example.com', $now);
$link = $urls->make($id, 'hello@example.com', $now);
check($params['id'] === '1', 'Signed link includes user ID');
check($params['user'] === hash('sha256', 'hello@example.com'), 'user is a SHA-256 email digest');
check(!str_contains($link, 'hello@example.com'), 'Signed link does not expose plaintext email');
check($params['expiration'] === (string) ($now + 1800), 'Exact 30 minute expiry');
check($urls->validSignature($params), 'Canonical HMAC signature verifies');
check(parse_url($link, PHP_URL_PATH) === '/verify/email', 'Correct signed GET route');
foreach (['id' => '2', 'expiration' => (string) ($now + 1801), 'user' => str_repeat('a', 64), 'signature' => str_repeat('f', 64)] as $key => $value) {
    $tampered = $params;
    $tampered[$key] = $value;
    check(!$urls->validSignature($tampered), 'Reject tampered field: ' . $key);
}
$extra = $params;
$extra['unexpected'] = 'x';
check(!$urls->validSignature($extra), 'Reject extra query parameter');
check(!$urls->validSignature(['id' => '1']), 'Reject incomplete query');
check($service->verify($params, $now + 1800) === 'expired', 'Reject at expiry boundary');
check($users->findById($id)['email_verified'] === 0, 'Expired link does not update database');
check($service->verify($params, $now) === 'verified', 'Valid link verifies');
check($users->findById($id)['email_verified'] === 1, 'Verification sets email_verified=1');
check($service->verify($params, $now) === 'verified', 'Clicking link twice is idempotent');
check($service->sendVerificationEmail($id, $now) === false, 'Verified account cannot resend');

$id2 = $users->create('before@example.com', password_hash('password-123456', PASSWORD_DEFAULT));
check($service->sendVerificationEmail($id2, $now), 'First verification email sends');
check(count($mailer->sent) === 1 && $mailer->sent[0]['to'] === 'before@example.com', 'Mailer recipient is saved email');
check(!$service->sendVerificationEmail($id2, $now + 59), 'Throttling rejects resend within 60s');
check($service->sendVerificationEmail($id2, $now + 60), 'Resend permitted after 60s');
$oldParams = $urls->parameters($id2, 'before@example.com', $now);
$users->changeEmailAndUnverify($id2, 'after@example.com');
check($service->verify($oldParams, $now + 61) === 'mismatch', 'Old link invalid after email change');
check(!$service->sendVerificationEmail($id2, $now + 61), 'Email change does not bypass send throttle');
check($service->sendVerificationEmail($id2, $now + 120), 'New address receives link after cooldown');
check($mailer->sent[2]['to'] === 'after@example.com', 'Mail sent to changed address');
parse_str(parse_url($mailer->sent[2]['url'], PHP_URL_QUERY) ?: '', $newParams);
check($service->verify($newParams, $now + 120) === 'verified', 'New address link validates');
check($users->findById($id2)['email_verified'] === 1, 'New email marked verified');
check((new RequireVerified())->check($users->findById($id2)) === null, 'Verified user passes protected middleware');
check((new RequireVerified())->check(null)?->headers['Location'] === '/login', 'Guest is redirected to login');

$nowRef = $now;
$app = new App($users, $service, static function () use (&$nowRef): int { return $nowRef; });
check($app->handle(req('GET', '/dashboard'))->status === 303 && $app->handle(req('GET', '/dashboard'))->headers['Location'] === '/login', 'Guest cannot access dashboard');
check($app->handle(req('POST', '/register', ['email' => 'x@example.com', 'password' => 'password-123456']))->status === 419, 'CSRF is enforced');
$csrf = Csrf::token();
check($app->handle(req('POST', '/register', ['csrf' => $csrf, 'email' => 'bad-email', 'password' => 'password-123456']))->status === 422, 'Invalid registration email is rejected');
check($app->handle(req('POST', '/register', ['csrf' => $csrf, 'email' => 'new@example.com', 'password' => 'short']))->status === 422, 'Weak signup password rejected');
$res = $app->handle(req('POST', '/register', ['csrf' => $csrf, 'email' => 'new@example.com', 'password' => 'password-123456']));
check($res->status === 303 && $res->headers['Location'] === '/verify/resend', 'Registration redirects to verification page');
$newId = $_SESSION['user_id'];
check($users->findById($newId)['email_verified'] === 0, 'New account starts unverified');
check($app->handle(req('GET', '/dashboard'))->headers['Location'] === '/verify/resend', 'Unverified account blocked from dashboard');
check($app->handle(req('GET', '/verify/resend'))->status === 200, 'Unverified user sees resend form');
check($app->handle(req('POST', '/verify/resend', ['csrf' => $csrf, 'email' => 'new@example.com']))->status === 303, 'Resend action is accepted');
$recentUrl = end($mailer->sent)['url'];
parse_str(parse_url($recentUrl, PHP_URL_QUERY) ?: '', $activeQuery);
check($app->handle(req('GET', '/verify/email', [], $activeQuery))->headers['Location'] === '/dashboard', 'Signed verification redirects to dashboard');
check($app->handle(req('GET', '/dashboard'))->status === 200, 'Verified user may access dashboard');
check($app->handle(req('GET', '/verify/resend'))->headers['Location'] === '/dashboard', 'Already verified account not shown resend');
check($app->handle(req('GET', '/verify/email', [], ['foo' => 'bar']))->status === 403, 'Invalid signed URL gives HTTP 403');
$expParams = $urls->parameters($newId, 'new@example.com', $now);
$nowRef += 1800;
check($app->handle(req('GET', '/verify/email', [], $expParams))->status === 410, 'Expired signed URL gives HTTP 410');
check($app->handle(req('GET', '/not-found'))->status === 404, 'Unknown URL returns 404');
check($app->handle(req('POST', '/logout', ['csrf' => $csrf]))->headers['Location'] === '/login', 'Logout clears authentication');
check(!isset($_SESSION['user_id']), 'No session ID after logout');
check($app->handle(req('POST', '/login', ['csrf' => $csrf, 'email' => 'new@example.com', 'password' => 'incorrect-password']))->status === 401, 'Incorrect password rejected');
check($app->handle(req('POST', '/login', ['csrf' => $csrf, 'email' => 'new@example.com', 'password' => 'password-123456']))->headers['Location'] === '/dashboard', 'Verified login allows dashboard');
check(str_contains(\App\View::resend('x"><script>alert(1)</script>'), '&lt;script&gt;'), 'Email rendered with HTML escaping');

if ($failures !== []) {
    foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
    fwrite(STDERR, "FAILED: " . count($failures) . "/$count checks.\n");
    exit(1);
}
echo "PASS: $count assertions (fake repository and fake mailer; no SQLite/SMTP integration).\n";
