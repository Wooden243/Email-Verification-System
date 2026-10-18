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
