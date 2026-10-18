<?php
declare(strict_types=1);

namespace App;

use App\Http\Middleware\RequireVerified;
use App\Http\Request;
use App\Http\Response;
use App\Persistence\UserRepository;
use App\Security\Csrf;
use App\Service\VerificationService;
use PDOException;

final class App
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly VerificationService $verification,
        private readonly \Closure $clock
    ) {}

    public function handle(Request $request): Response
    {
        if ($request->method === 'POST' && !Csrf::valid($request->form['csrf'] ?? null)) {
            return $this->page('送信エラー', '<h1>不正なリクエスト</h1><p>ページを読み込み直してください。</p>', 419);
        }
        $user = $this->currentUser();
        $route = $request->method . ' ' . $request->path;
        switch ($route) {
            case 'GET /':
                return $user !== null ? Response::redirect('/dashboard') : Response::redirect('/register');
            case 'GET /register':
                return $this->page('新規登録', View::register(), 200, $user);
            case 'POST /register':
                return $this->register($request);
            case 'GET /login':
                return $this->page('サインイン', View::login(), 200, $user);
            case 'POST /login':
                return $this->login($request);
            case 'POST /logout':
                unset($_SESSION['user_id']);
                session_regenerate_id(true);
                $this->flash('ログアウトしました。');
                return Response::redirect('/login');
            case 'GET /verify/email':
                return $this->verify($request);
            case 'GET /verify/resend':
                if ($user === null) {
                    return Response::redirect('/login');
                }
                return (int) $user['email_verified'] === 1
                    ? Response::redirect('/dashboard')
                    : $this->page('認証メールの再送', View::resend((string) $user['email']), 200, $user);
            case 'POST /verify/resend':
                return $this->resend($request, $user);
            case 'GET /dashboard':
                $rejection = (new RequireVerified())->check($user);
                return $rejection ?? $this->page('ダッシュボード', View::dashboard((string) $user['email']), 200, $user);
            default:
                return $this->page('ページが見つかりません', '<h1>404 Not Found</h1><p>指定されたページは存在しません。</p>', 404, $user);
        }
    }

    private function register(Request $request): Response
    {
        $email = self::normalizeEmail($request->form['email'] ?? null);
        $password = $request->form['password'] ?? null;
        if ($email === null || !is_string($password) || strlen($password) < 12 || strlen($password) > 4096) {
            return $this->error('有効なメールアドレスと12文字以上のパスワードを指定してください。', 422);
        }
        try {
            $id = $this->users->create($email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw $e;
            }
            return $this->error('このメールアドレスは既に登録されています。', 409);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        try {
            $this->verification->sendVerificationEmail($id, ($this->clock)());
            $this->flash('認証メールを送信しました。');
        } catch (\Throwable $e) {
            error_log('Registration mail error: ' . $e->getMessage());
            $this->flash('アカウントは作成されましたが送信に失敗しました。設定を確認して再送してください。');
        }
        return Response::redirect('/verify/resend');
    }

    private function login(Request $request): Response
    {
        $email = self::normalizeEmail($request->form['email'] ?? null);
        $password = $request->form['password'] ?? null;
        $user = $email === null ? null : $this->users->findByEmail($email);
        if ($user === null || !is_string($password) || !password_verify($password, (string) $user['password_hash'])) {
            return $this->error('メールアドレスまたはパスワードが違います。', 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        return (int) $user['email_verified'] === 1
            ? Response::redirect('/dashboard') : Response::redirect('/verify/resend');
    }

    private function verify(Request $request): Response
    {
        $status = $this->verification->verify($request->query, ($this->clock)());
        if ($status === 'verified') {
            $this->flash('メールアドレスの認証が完了しました。');
            return Response::redirect('/dashboard');
        }
        if ($status === 'expired') {
            return $this->error('認証リンクの有効期限が切れています。サインインして再送信してください。', 410);
        }
        return $this->error('認証リンクが無効です。再送信したリンクをご利用ください。', 403);
    }

    /** @param array<string,mixed>|null $user */
    private function resend(Request $request, ?array $user): Response
    {
        if ($user === null) {
            return Response::redirect('/login');
        }
        if ((int) $user['email_verified'] === 1) {
            return Response::redirect('/dashboard');
        }
        $email = self::normalizeEmail($request->form['email'] ?? null);
        if ($email === null) {
            return $this->error('有効なメールアドレスを入力してください。', 422, $user);
        }
        if ($email !== $user['email']) {
            try {
                $this->users->changeEmailAndUnverify((int) $user['id'], $email);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw $e;
                }
                return $this->error('指定されたメールアドレスを使用できません。', 409, $user);
            }
        }
        try {
            if (!$this->verification->sendVerificationEmail((int) $user['id'], ($this->clock)())) {
                $this->flash('再送信には60秒の間隔が必要です。時間をおいて再試行してください。');
            } else {
                $this->flash('認証メールを送信しました。');
            }
        } catch (\Throwable $e) {
            error_log('Resend mail error: ' . $e->getMessage());
            $this->flash('送信に失敗しました。メール設定を確認してください。');
        }
        return Response::redirect('/verify/resend');
    }

    /** @return array<string,mixed>|null */
    private function currentUser(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) && $id > 0 ? $this->users->findById($id) : null;
    }

    private static function normalizeEmail(mixed $input): ?string
    {
        if (!is_string($input)) {
            return null;
        }
        $email = strtolower(trim($input));
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function flash(string $text): void
    {
        $_SESSION['flash'] = $text;
    }

    /** @param array<string,mixed>|null $user */
    private function error(string $message, int $status, ?array $user = null): Response
    {
        return $this->page('処理エラー', '<h1>処理できませんでした</h1><p>' . View::escape($message) . '</p>', $status, $user);
    }

    /** @param array<string,mixed>|null $user */
    private function page(string $title, string $content, int $status = 200, ?array $user = null): Response
    {
        return new Response($status, View::page($title, $content, $user));
    }
}
