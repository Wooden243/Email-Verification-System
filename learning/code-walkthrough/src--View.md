# src/View.php — HTML生成

> 出典: プロジェクト内の `src/View.php`。SHA-256: `134ebba09c77edbfb415dbeea6a5dc100c62d814787e855473dbb79fb7917f45`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：エスケープしたHTML画面とCSRF付きフォームを組み立てる。
- **入力**：画面名、ユーザー情報
- **出力・副作用**：HTML文字列
- **他ファイルとの関係**：App, Csrf
- **前提知識**：HTMLエスケープ、フォーム、XSS

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App;

use App\Security\Csrf;

final class View
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string,mixed>|null $user */
    public static function page(string $title, string $content, ?array $user = null): string
    {
        $title = self::escape($title);
        $notification = '';
        if (isset($_SESSION['flash'])) {
            $notification = '<div class="notice" role="status">' . self::escape((string) $_SESSION['flash']) . '</div>';
            unset($_SESSION['flash']);
        }
        $nav = $user === null
            ? '<a href="/login">サインイン</a><a href="/register">新規登録</a>'
            : '<a href="/dashboard">ダッシュボード</a><form method="post" action="/logout"><input type="hidden" name="csrf" value="' . Csrf::token() . '"><button type="submit" class="nav-button">ログアウト</button></form>';
        return '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . ' | Email Verification System</title>'
            . '<link rel="stylesheet" href="/styles.css"></head><body>'
            . '<header><a class="brand" href="/">Email Verification System</a><nav>' . $nav . '</nav></header>'
            . '<main class="container">' . $notification . '<article class="panel">' . $content . '</article></main>'
            . '<footer>Signed URL · HMAC-SHA256 · 30-minute expiration</footer></body></html>';
    }

    public static function register(): string
    {
        $csrf = Csrf::token();
        return '<span class="eyebrow">CREATE ACCOUNT</span><h1>新規登録</h1><p>登録後、メールアドレスの確認が必要です。</p>'
            . '<form method="post" action="/register"><input type="hidden" name="csrf" value="' . $csrf . '">'
            . '<label>メールアドレス<input required type="email" maxlength="254" name="email" autocomplete="email" placeholder="you@example.com"></label>'
            . '<label>パスワード（12文字以上）<input required type="password" minlength="12" name="password" autocomplete="new-password"></label>'
            . '<button type="submit">アカウントを作成</button></form><p class="subtle">既に登録済みの場合は<a href="/login">サインイン</a></p>';
    }

    public static function login(): string
    {
        return '<span class="eyebrow">WELCOME BACK</span><h1>サインイン</h1>'
            . '<form method="post" action="/login"><input type="hidden" name="csrf" value="' . Csrf::token() . '">'
            . '<label>メールアドレス<input required type="email" name="email" autocomplete="email"></label>'
            . '<label>パスワード<input required type="password" name="password" autocomplete="current-password"></label>'
            . '<button type="submit">サインイン</button></form>';
    }

    public static function resend(string $email): string
    {
        return '<span class="eyebrow">ACTION REQUIRED</span><h1>メール認証が必要です</h1>'
            . '<p>登録先メールアドレスに確認リンクを送信しています。リンクの有効期限は発行から30分です。</p>'
            . '<p class="info">送信先：<strong>' . self::escape($email) . '</strong></p>'
            . '<form method="post" action="/verify/resend"><input type="hidden" name="csrf" value="' . Csrf::token() . '">'
            . '<label>送信先メールアドレス（必要に応じて変更）<input required type="email" maxlength="254" name="email" value="' . self::escape($email) . '"></label>'
            . '<button type="submit">認証メールを再送信</button></form>'
            . '<p class="subtle">再送信は60秒に1回までです。開発環境ではメールはログに記録されます。</p>';
    }

    public static function dashboard(string $email): string
    {
        return '<span class="eyebrow">VERIFIED ACCOUNT</span><h1>ダッシュボード</h1>'
            . '<p>このページは、メール認証済みのユーザーだけが閲覧できます。</p>'
            . '<p class="info">認証済み：<strong>' . self::escape($email) . '</strong></p>'
            . '<p class="subtle">認証ミドルウェアにより、未認証アカウントは再送信ページへ転送されます。</p>';
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | 画面へ出す文字列をエスケープしHTML注入を防ぐ。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L16–L20 | セッションに結びついたユーザー状態またはCSRFトークンを操作する。 |
| L21–L25 | セッションに結びついたユーザー状態またはCSRFトークンを操作する。 |
| L26–L30 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L31–L35 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L36–L40 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L41–L45 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L46–L50 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L51–L55 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L56–L60 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L61–L65 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L66–L70 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L71–L73 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |

### メソッドと引数・戻り値の関係

- **`escape()` (L10–L15)**：htmlspecialcharsで&、引用符、タグの特殊文字をエスケープしてXSSを防ぐ。
- **`page()` (L16–L35)**：画面生成View::pageをResponseオブジェクトで包み、HTTP送信とは分離する。
- **`register()` (L36–L45)**：メールとパスワードを検査し、hash付きでユーザーを作成。セッション再生成後に認証メールを送信して再送画面へ転送する。
- **`login()` (L46–L54)**：メールとパスワードハッシュを照合し、ログイン後にemail_verifiedの値で遷移先を選ぶ。
- **`resend()` (L55–L65)**：認証前ユーザーに限定し、入力メールの変更を反映してから発行・送信する。再送制限や送信エラーを安全なメッセージで通知する。
- **`dashboard()` (L66–L73)**：認証成功ユーザーのメールアドレスをエスケープして保護ページを組み立てる。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（画面名、ユーザー情報）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（HTML文字列）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：エスケープしたHTML画面とCSRF付きフォームを組み立てる。
2. **入力**：画面名、ユーザー情報。
3. **出力**：HTML文字列。
4. **依存先**：App, Csrf。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
