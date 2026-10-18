# 04 — デバッグガイド

## 最初に確認すること

1. `php -v` と `php -m | grep -i pdo_sqlite` で必要なPHPとSQLite拡張があるか確認。
2. `php scripts/setup.php` で鍵とDBを生成。`.env`に64桁のAPP_KEYがあるか確認（値を人に公開しない）。
3. `php tests/run.php` でテストを実行。
4. `php -S 127.0.0.1:8000 -t public public/router.php` の標準エラーに例外ログがないか確認。
5. 開発モードなら`php scripts/show-last-link.php`で最新URLを確認。受信できないのではなくログ方式を選んでいる場合がある。

## エラーと調査先

| 現象 | 原因候補 | 確認・解決 |
|---|---|---|
| `pdo_sqlite is required` | PHP CLIにSQLite PDO拡張がない | `php -m`, `sudo apt install php-sqlite3` |
| `Database missing` | セットアップしていない | `php scripts/setup.php`を実行 |
| `APP_KEY must be 64 hexadecimal...` | `.env`のAPP_KEYが空か形式不正 | `.env`を確認。初回のみsetupで生成。既存DBの有効リンクを維持するなら鍵を不用意に再生成しない |
| HTTP 403（検証リンク） | 署名改竄、余分なパラメーター、旧メール宛リンク | URL全体を再コピー、再送、`SignedUrl::validSignature`を確認 |
| HTTP 410（検証リンク） | `expiration <= now` | 期限切れなので再送。サーバーの時計が正確かも確認 |
| `/dashboard`から`/verify/resend`へ | `email_verified=0` | ログの最新URLを開く。`RequireVerified::check`を確認 |
| 再送してもログが増えない | 60秒の送信間隔、または既に認証済み | `last_verification_sent_at`と`email_verified`を確認 |
| 500 Server error | DBや秘密鍵などの起動失敗 | PHPサーバーの標準エラー、`storage/`の書込権限を確認 |
| Composerのクラスがない | PHPMailer未インストール | `composer install`、`vendor/autoload.php`を確認 |
| SMTP送信失敗 | SMTP_HOST/資格情報/通信制限/TLS不一致 | メールサービスの設定、送信許可、ネットワークを確認 |
| `419 不正なリクエスト` | CSRF不一致、セッション期限切れ | ページ再読込、Cookieの許可、`Csrf::valid`を確認 |
| `Address already in use` | 開発サーバーのポートが使用中 | 既存のサーバーを止めるか`.env`のAPP_URLとポートを揃えて変更 |

## コードでログを調べる

`src/App.php`では送信時の例外メッセージを`error_log`へ記録します。利用者には詳細なSMTP認証情報やスタックトレースを表示しません。

### 原因の切り分け例

「メール認証リンクが無効」なら、`SignedUrl::validSignature`の成否、`ValidateExpiration`の成否、`SqliteUserRepository::markVerifiedIfEmailMatches`のメールハッシュ一致の順に切り分けます。ログを増やすときは**URL内の署名値や個人のメールアドレスを本番ログに残しすぎない**ことに注意してください。

### 自力で進める手順

再現手順を固定する → 期待値と実際の値を並べる → 入口（HTTP/フォーム）から呼び出し順を追う → 最初に期待値と食い違った箇所を特定する → 1箇所だけ修正する → 自動テストを再実行する → 回帰していないか確認する。
