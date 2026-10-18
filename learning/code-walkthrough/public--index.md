# public/index.php — HTTPエントリーポイント

> 出典: プロジェクト内の `public/index.php`。SHA-256: `f3f9ddf2497353037db1741d1887c09da5516fcf6d775a66d30d034f33e288c7`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：Webサーバーから受け取ったリクエストをアプリへ渡し、DB・メール送信を組み立てる。
- **入力**：HTTP環境変数、セッションCookie、.env
- **出力・副作用**：HTTPレスポンス
- **他ファイルとの関係**：Config, Database, SqliteUserRepository, SignedUrl, VerificationService, App
- **前提知識**：PHPのリクエストライフサイクル・セッション

## ② ソースコード全文（省略なし）

```php
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
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L16–L20 | HTTPの状態コードを設定する。 |
| L21–L25 | HTTPの状態コードを設定する。 |
| L26–L30 | ブラウザに送るHTTPヘッダーを設定する。 |
| L31–L35 | ブラウザに送るHTTPヘッダーを設定する。 |
| L36–L40 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L41–L45 | HTTPの状態コードを設定する。 ブラウザに送るHTTPヘッダーを設定する。 内部エラーをサーバーログに記録する。画面へ秘密情報を出さない。 |
| L46–L47 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **トップレベル処理**：Web/CLIの入口から終了まで上から順に実行。宣言以外の関数呼び出し順と副作用を確認する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（HTTP環境変数、セッションCookie、.env）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（HTTPレスポンス）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：Webサーバーから受け取ったリクエストをアプリへ渡し、DB・メール送信を組み立てる。
2. **入力**：HTTP環境変数、セッションCookie、.env。
3. **出力**：HTTPレスポンス。
4. **依存先**：Config, Database, SqliteUserRepository, SignedUrl, VerificationService, App。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
