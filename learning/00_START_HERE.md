# 00 — 最初に読む：3分で把握する

## 3分で分かる全体像

メール認証は、「フォームに入力されたアドレスをその人が受信できるか」を確認する処理です。登録時の入力だけでは所有の証明になりません。本課題では、登録先に一時的な署名付きリンクを送り、受信者がアクセスして初めて認証済みにします。

1. **登録**：`POST /register`でメールとパスワードを受け取り、DBに`email_verified=0`のユーザーを追加する。
2. **URL発行**：サーバーがユーザーID、メールハッシュ、有効期限（現在時刻＋1800秒）をHMACで署名する。
3. **送信**：`Mailer`が署名付きURLを認証メールとして送る。開発時はログへ記録し、本番向けにPHPMailerによるSMTP実装がある。
4. **URLアクセス**：`GET /verify/email`を署名ミドルウェア→期限ミドルウェア→登録メール照合の順に処理する。
5. **認証完了**：DBの`email_verified=1`へ更新する。
6. **保護ページ**：`GET /dashboard`で認証済みか確認。未認証なら`/verify/resend`へ移動し、再送・アドレス変更ができる。

## 学べる技術

| 重要度 | 技術 | 目的 | ファイル |
|---|---|---|---|
| **必須** | HTTPルーティング/GET/POST | URLから適切な処理に振り分ける | `src/App.php`, `public/index.php` |
| **必須** | HMAC署名と署名検証 | URLの改竄を検出する | `src/Security/SignedUrl.php` |
| **必須** | ミドルウェアとアクセス制御 | 特定の前提を満たすときだけ処理を通す | `src/Http/Middleware/` |
| **必須** | DB更新と認証状態 | 永続化された`email_verified`を更新 | `src/Persistence/SqliteUserRepository.php` |
| **重要** | CSRFとセッション | ログイン状態管理と不正POST防止 | `src/Security/Csrf.php`, `src/App.php` |
| **重要** | メール送信インターフェース | 本物SMTPと開発用ログを差し替える | `src/Mail/` |
| 参考 | HTML/CSS | 入力画面と検証状態の表示 | `src/View.php`, `public/styles.css` |

## 最重要コード・関数

- `SignedUrl::parameters()` と `SignedUrl::validSignature()`：何を署名するか、何を検証するか。
- `VerificationService::sendVerificationEmail()` と `verify()`：一連の処理の中心。
- `SqliteUserRepository::markVerifiedIfEmailMatches()`：古いメール宛リンクが不正に成功しないよう、データを読み、条件付きで更新する。
- `App::handle()` と `RequireVerified::check()`：HTTPリクエストの振り分け・アクセス制限。

## 推奨するファイル閲覧順序

`App.php`（経路全体） → `VerificationService.php` → `SignedUrl.php` → `ValidateSignature.php`/`ValidateExpiration.php` → `SqliteUserRepository.php` → `PhpMailerAdapter.php` → `View.php`。

## 短時間の学習コース

| 時間 | 学習範囲 | 到達目標 |
|---|---|---|
| 15分 | 本書、`01_ARCHITECTURE.md`の図、`03_EXECUTION_FLOW.md` | 登録から認証までを説明できる |
| 30分 | 上記＋`SignedUrl.php`と`VerificationService.php`の全文解説 | 署名・期限・送信と認証の関係を説明できる |
| 60分 | 上記＋`App.php`、`SqliteUserRepository.php`、`05_REVIEW.md` | 主要処理とエラー・DB・アクセス制御を説明できる |
| 制限なし | 全ファイルの`code-walkthrough/`と演習 | コードの変更・自力デバッグができる |

## 進路別の理解の優先度（一般的な技術領域別）

- **SWE**：HTTP、認証状態、DB、例外処理、テスト、セキュリティ、開発環境を重点的に学ぶ。
- **AI/MLエンジニア**：Web APIの認証・アクセス制御、DB、サービスの分割、テストの考え方は学習価値が高い。フォームの装飾は後回しでよい。
- **フロントエンド（FE）**：GET/POST、フォーム、リダイレクト、セッション、CSRF、ブラウザとサーバーの境界を重点的に学ぶ。

## 今すぐ暗記する必要がない内容

SMTP各社の詳細設定、CSSの細かな値、`filter_var`の全オプション、PHPMailerのメソッド名の暗記は不要です。ただし、**送信を担当する箇所、秘密鍵と公開される署名の違い、有効期限の条件、DBを変更するタイミング**は説明できるようにしてください。
