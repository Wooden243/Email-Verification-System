# 要件定義とトレーサビリティ

## 課題文の読み取り

**課題名**: Email Verification System（Recursion / Servers with Databases 第5章11/14）

**概要**: PHPで署名付きURLを用いた安全なメール認証システムを実装し、compart-partsアプリを拡張する。署名付きURLは一時的であり、有効性・真正性を保証する。

**指定技術**: 本文にPHP、PHPMailer、GET `/verify/email`、認証・期限のミドルウェア、DBの`email_verified`、30分の有効期限が明示されている。スクリーンショット右上に「JavaScript (Node.js 12.14.0)」の選択UIも存在するが、課題本文の実装技術を優先した。

**提出条件**: GitHubへアップロードし、リポジトリのURLを提出。課題ページの説明では「公開リポジトリ」のURLを求めている。ユーザーの元アプリのコードは提供されていないため、本プロジェクトは独立した参照実装であり、compart-partsへの直接パッチではない。

## 要件トレーサビリティ

| ID | 課題文の要求 | 実装ファイル・関数 | 確認方法 | 結果 / 注意点 |
|---|---|---|---|---|
| R-01 | `GET /verify/email` が `id`, `user`, `expiration`, `signature` を受け入れる | `src/App.php::verify`, `src/Security/SignedUrl.php` | テストでURL生成とGET要求 | フェイクDBを用いる50項目のPHPテスト内で確認 |
| R-02 | `user`はメールアドレスのハッシュで、プレーンテキストではない | `SignedUrl::emailDigest` | URL中のemail非露出とSHA-256一致 | 自動検証済み |
| R-03 | ミドルウェアで署名を検証 | `Http/Middleware/ValidateSignature.php`, `SignedUrl::validSignature` | パラメーター改竄、不足、追加 | 自動検証済み |
| R-04 | ミドルウェアで有効期限を検証 | `Http/Middleware/ValidateExpiration.php` | 1800秒境界の期限切れ | 自動検証済み |
| R-05 | URL上の情報とユーザー情報が一致するか確認 | `SqliteUserRepository::markVerifiedIfEmailMatches` | メール変更後の旧リンク拒否 | インメモリで検証、SQLite連携は未検証 |
| R-06 | DBの`email_verified`列を更新 | `scripts/schema.sql`, `SqliteUserRepository` | 認証成功後のフラグ確認 | インメモリで検証、SQLite連携は未検証 |
| R-07 | 登録時に署名付きURLを生成して送信 | `App::register`, `VerificationService::sendVerificationEmail` | 送信の有無と宛先 | フェイクMailerで検証 |
| R-08 | URLに`id`, `user`, `expiration`をカプセル化 | `SignedUrl::parameters/make` | パラメーターと署名を検査 | 自動検証済み |
| R-09 | PHPMailerで登録先へ認証メールを送信 | `Mail/PhpMailerAdapter.php` | `composer install` 後のSMTP動作試験 | コード実装済み。実SMTP配送は未検証 |
| R-10 | 30分でリンクが期限切れ | `SignedUrl::parameters`, `ValidateExpiration` | 正確な秒数と期限境界 | 自動検証済み |
| R-11 | 未認証ユーザーがページにアクセスすると再送ページへ | `Http/Middleware/RequireVerified.php`, `App::handle` | `/dashboard`へアクセス | 自動検証済み |
| R-12 | `/verify/resend`で新しい検証メール送信ボタン | `View::resend`, `App::resend` | 登録後に再送POST | フェイクMailerで検証 |
| R-13 | 再送した新リンクも30分で期限切れ | `VerificationService::sendVerificationEmail` → `SignedUrl::make` | 時刻を進めて再生成 | 自動検証済み |
| R-14 | 未認証者はメールアドレスを変更してその宛先に再送できる | `App::resend`, `changeEmailAndUnverify` | 変更後の旧リンク拒否と新宛先 | 自動検証済み |
| R-15 | バックエンド開発内容を見直す | `learning/`, `code-walkthrough/` | 説明・演習・完全ソース比較 | 教材作成・整合性検査 |
| R-16 | GitHubリポジトリURLで提出 | README Git手順 | GitHub実画面で確認 | 手順記載のみ。pushは未実施 |

## 課題に直接書かれていない、実装に必要な仮定

- `user`のハッシュアルゴリズム指定は画像内にないため、SHA-256とした。署名には別途HMAC-SHA256を使用する。
- 元のcompart-partsアプリがないため、PHP・SQLite・セッションで最小限のユーザー登録/ログインを実装した。
- 未認証ユーザーをブロックする例として`/dashboard`を用意した。
- 再送の乱用を抑えるため、課題外の追加保護として60秒の再送間隔を採用した。
- メールを送信せずに試験できる`MAIL_TRANSPORT=log`を追加した。本番SMTPはPHPMailer経由。
- `APP_URL`はポートを含む**オリジン**（スキーム＋ホスト＋任意のポート）とし、パスを含めない。

## 検証不能・未対応事項

- 既存のcompart-partsアプリへの直接統合（元のソースコードがない）。
- 実際のSMTPメール到達確認、SQLiteのPDO接続を含むHTTP結合試験（検証環境の制約）。
- 公開サーバーへの配置・GitHubへの認証済みpush（ユーザーの外部環境へのアクセス権がない）。
