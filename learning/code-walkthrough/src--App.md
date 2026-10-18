# src/App.php — HTTPルーティングとアプリ制御

> 出典: プロジェクト内の `src/App.php`。SHA-256: `ae6f53160b0ded9f8550f1f90c6270edec1544efae1c54b4481cdb97ba641a16`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：登録・ログイン・ログアウト・認証URL・再送・保護ページをURL/HTTPメソッドごとに処理する。
- **入力**：Request、$_SESSION
- **出力・副作用**：Response、登録/認証/送信の状態変化
- **他ファイルとの関係**：UserRepository, VerificationService, View, Csrf, RequireVerified
- **前提知識**：GET/POST、Cookieセッション、リダイレクト、例外

## ② ソースコード全文（省略なし）

```php
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
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L16–L20 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L21–L25 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L26–L30 | `GET /`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 HTTPメソッドとURLによるルート分岐が始まる。 このHTTPルートに対応する処理を開始する。 |
| L31–L35 | `GET /register`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 302ではなく303を使い、POST後の再取得をGETにする。 このHTTPルートに対応する処理を開始する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L36–L40 | `GET /login`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 このHTTPルートに対応する処理を開始する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L41–L45 | `GET /verify/email`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 ログイン・登録による権限変更時にセッション固定攻撃のリスクを抑える。 |
| L46–L50 | `GET /verify/resend`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 このHTTPルートに対応する処理を開始する。 |
| L51–L55 | `POST /verify/resend`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 302ではなく303を使い、POST後の再取得をGETにする。 このHTTPルートに対応する処理を開始する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L56–L60 | `GET /dashboard`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 このHTTPルートに対応する処理を開始する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L61–L65 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L66–L70 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L71–L75 | パスワードを平文ではなくハッシュとして保存する。 例外を捕捉し、失敗状態を処理する。 |
| L76–L80 | ログイン・登録による権限変更時にセッション固定攻撃のリスクを抑える。 セッションに結びついたユーザー状態またはCSRFトークンを操作する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L81–L85 | 宛先と署名付きURLをMailerの実装へ渡す。 内部エラーをサーバーログに記録する。画面へ秘密情報を出さない。 例外を捕捉し、失敗状態を処理する。 |
| L86–L90 | 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L91–L95 | リポジトリ経由で現在のユーザー情報を読み取る。 |
| L96–L100 | パスワードを平文ではなくハッシュとして保存する。 保存されたパスワードハッシュと入力されたパスワードの一致を検査。 ログイン・登録による権限変更時にセッション固定攻撃のリスクを抑える。 |
| L101–L105 | 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L106–L110 | 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L111–L115 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L116–L120 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L121–L125 | 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L126–L130 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L131–L135 | 宛先変更をDBへ反映し、認証フラグを未確認に戻す。 例外を捕捉し、失敗状態を処理する。 |
| L136–L140 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L141–L145 | 宛先と署名付きURLをMailerの実装へ渡す。 |
| L146–L150 | 内部エラーをサーバーログに記録する。画面へ秘密情報を出さない。 例外を捕捉し、失敗状態を処理する。 |
| L151–L155 | 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L156–L160 | セッションに結びついたユーザー状態またはCSRFトークンを操作する。 リポジトリ経由で現在のユーザー情報を読み取る。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L161–L165 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L166–L170 | サーバー側でメールアドレス書式を検査する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L171–L175 | セッションに結びついたユーザー状態またはCSRFトークンを操作する。 |
| L176–L180 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L181–L185 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L186–L186 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L16–L21)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`handle()` (L22–L63)**：POST全体のCSRFを先に検証してから、HTTPメソッドとパスを組み合わせた文字列で登録・ログイン・検証・保護画面へ分岐する。
- **`register()` (L64–L90)**：メールとパスワードを検査し、hash付きでユーザーを作成。セッション再生成後に認証メールを送信して再送画面へ転送する。
- **`login()` (L91–L104)**：メールとパスワードハッシュを照合し、ログイン後にemail_verifiedの値で遷移先を選ぶ。
- **`verify()` (L105–L118)**：署名検証→期限検証→現在メール照合とDB更新の順番で実行する。返り値はinvalid/expired/mismatch/verified。
- **`resend()` (L119–L154)**：認証前ユーザーに限定し、入力メールの変更を反映してから発行・送信する。再送制限や送信エラーを安全なメッセージで通知する。
- **`currentUser()` (L155–L160)**：セッションのuser_idを検査し、DBから現在のユーザー情報を取得する。ログインしていないときはnull。
- **`normalizeEmail()` (L161–L169)**：POST入力が文字列であることを確認し、空白と大文字小文字を正規化してメール書式・最大長を検査する。
- **`flash()` (L170–L175)**：一度きりの通知文を$_SESSIONに格納する。画面を表示する際にView::pageで読み取り後削除される。
- **`error()` (L176–L181)**：任意のエラーテキストをHTMLエスケープし、指定したHTTP状態コードを含む画面を返す。
- **`page()` (L182–L186)**：画面生成View::pageをResponseオブジェクトで包み、HTTP送信とは分離する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：`POST /register`で`email=alice@example.com, password=(12文字以上)`を受け取り、`create()`の戻り値が`id=7`なら`$_SESSION["user_id"]=7`となる。メール送信後は303で`/verify/resend`へ遷移する。`GET /dashboard`では`email_verified=0`なら閲覧不可。

## ⑤ 最重要部分の再確認

1. **責務**：登録・ログイン・ログアウト・認証URL・再送・保護ページをURL/HTTPメソッドごとに処理する。
2. **入力**：Request、$_SESSION。
3. **出力**：Response、登録/認証/送信の状態変化。
4. **依存先**：UserRepository, VerificationService, View, Csrf, RequireVerified。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
