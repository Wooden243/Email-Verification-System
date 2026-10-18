# Email Verification System

PHP 8.1以降で動作する、**HMAC-SHA256による署名付きURLを使ったメール認証システム**です。ユーザー登録・サインイン・認証メール送信・30分の有効期限・認証必須ページへのアクセス制限・再送信・認証前メールアドレス変更を実装しています。

> **課題との関係**：Recursion「Email Verification System」の4節の要件を実装した、単独実行できる参照プロジェクトです。課題文は既存のcompart-partsアプリへの拡張を想定していますが、元のアプリのコードは提供されていないため、登録・認証・画面を最小構成で新規実装しています。既存アプリに統合するには、認証・ユーザーテーブル・ルーティングの接続部分を調整してください。画面右上のNode.js選択表示よりも、課題本文のPHPおよびPHPMailer指定を優先しました。

## 主な機能

| 機能 | 実装 |
|---|---|
| `GET /verify/email` | `id`, `user`, `expiration`, `signature` を受信して署名・有効期限・DB登録メールを検証。`email_verified` を1へ更新 |
| 署名と期限のミドルウェア | `ValidateSignature`, `ValidateExpiration`。HMAC-SHA256、`hash_equals`、30分 |
| 登録時メール送信 | `VerificationService::sendVerificationEmail` → `Mailer`。SMTP設定ならPHPMailerで実メールを送信 |
| 未認証ユーザーのアクセス制限 | `RequireVerified` が `/dashboard` をブロックして `/verify/resend` に転送 |
| 再送・アドレス変更 | `/verify/resend`。任意の新しいメールアドレスを指定可能。再送はアカウントごとに60秒間隔 |

URL例（数値と署名は説明用の架空の値）：

```text
http://127.0.0.1:8000/verify/email?id=1&user=<メールアドレスのSHA-256値>&expiration=1792350600&signature=<HMAC-SHA256署名>
```

`user`はメールアドレスそのものではなく、**小文字化・前後空白削除後のメールアドレスのSHA-256ダイジェスト**です。署名対象は `/verify/email?id=...&user=...&expiration=...` であり、`signature` 自体は署名対象に含めません。署名キーは `.env` の `APP_KEY`（256ビット乱数）です。

## 必要環境

- **PHP 8.1以降**、`pdo_sqlite`、`openssl`（後者はSMTP接続で必要）、PHP CLI
- **Composer**：PHPMailerのインストール用（SMTP送信時に必要。開発用ログ送信のみなら不要）
- Windows + WSL2 Ubuntu（優先）。macOS / LinuxもPHPが使えれば可
- SQLite（PHPの`pdo_sqlite`拡張で利用）

### 1. WSL2 Ubuntuでの準備

WSL2のターミナルで以下を実行します。Ubuntuのパッケージ名はPHPの配布バージョンに応じて変わります。

```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-mbstring php-xml php-curl unzip composer
php -v
php -m | grep -i pdo_sqlite
```

プロジェクトをZIPから展開した場合：

```bash
unzip Email-Verification-System.zip
cd Email-Verification-System
php scripts/setup.php
```

`setup.php` は `.env` に**ランダムな `APP_KEY`** を生成し、`storage/app.sqlite` にテーブルを作成します。既存の`.env`は上書きしません。**`.env`とDBをGitHubに公開しないでください**。

PHPが `pdo_sqlite` を認識しない場合、PHPのバージョンと拡張を揃えて再インストールし、`php -m` で確認してください。

### 2. 開発環境で起動する（SMTP設定不要）

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

ブラウザで http://127.0.0.1:8000 を開き、アカウントを新規登録します。

デフォルトは `MAIL_TRANSPORT=log` のため、実際にはメールを送信せず、送信予定メールと認証URLを非公開ファイル `storage/dev-mails.log` に保存します。**開発用ログ出力はSMTP配信ではありません。**

別のターミナルで次のコマンドを実行し、表示されたURLをブラウザで開いてください。

```bash
cd <ZIPを展開したディレクトリ>/Email-Verification-System
php scripts/show-last-link.php
```

ページがダッシュボードへ遷移し、`email_verified=1` になります。開発サーバーは起動中のターミナルで `Ctrl+C` で停止します。

### 3. 実際のメールをPHPMailerで送信する

```bash
composer install
```

`.env` の次の設定を使用するSMTPサーバーに合わせて変更します。

```dotenv
MAIL_TRANSPORT=smtp
MAIL_FROM_ADDRESS=verified@example.com
MAIL_FROM_NAME=Email Verification System
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USERNAME=your-smtp-username
SMTP_PASSWORD=your-smtp-password
SMTP_ENCRYPTION=tls
```

SMTP認証情報は公開せず、実在するSMTPサーバーに設定してください。配信成功にはSMTP側の許可、メールアドレス、ネットワーク接続が必要です。ここでは外部SMTPへの送信は**未検証**です。

### 4. テストを実行する

```bash
bash scripts/quality-check.sh
# または個別に実行:
php tests/run.php
python3 tests/check_schema_sql.py
python3 scripts/validate-walkthrough.py
find src public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

`tests/run.php` は外部DB・SMTPを使わず、インメモリのフェイクリポジトリとフェイクメーラーにより、署名改竄、期限境界、再送間隔、メールアドレス変更、認証必須ページ、CSRF等を検査します。Python標準のSQLiteエンジンを使い、SQLの構文と実行結果も別途検証します（PHP PDO経由のテストではありません）。SQLiteとのPHP経由の結合確認は、`pdo_sqlite` が使える環境で`setup.php`とブラウザ操作による実際の登録〜認証で行います。検証済み／未検証の範囲は `docs/test-report.md` に記載しています。

## フォルダー構成

```text
Email-Verification-System/
├── public/                  # 公開ドキュメントルート、HTTPエントリーポイント、CSS
├── src/
│   ├── App.php              # HTTPルーティング、登録・サインイン等の制御
│   ├── View.php             # HTMLレンダリング（入力のエスケープ）
│   ├── Http/Middleware/     # 署名、期限、認証状態の検証
│   ├── Security/            # 署名付きURL、CSRF
│   ├── Service/             # 認証のアプリケーションロジック
│   ├── Persistence/         # SQLiteへのアクセスとインターフェース
│   └── Mail/                # PHPMailer/開発ログ/抽象化
├── scripts/                 # セットアップ、DBスキーマ、開発メール確認
├── tests/                   # 自動検証（外部サービス不要）
├── docs/                    # 要件とテスト報告
├── learning/                # 学習教材・全文コード解説
├── storage/                 # SQLiteファイル・メールログ（Git管理対象外）
├── .env.example             # 環境変数の例（秘密情報なし）
├── .gitignore
└── composer.json
```

`public` 以外をドキュメントルートに設定しないでください。メールログ、SQLite、`.env` の漏洩を避けます。

## GitHubに提出する

課題ページには公開リポジトリのURLの入力欄があります。GitHubへの実際のアップロードは行っていません。以下を**作業者の端末上で**実行します。

### 新しいリポジトリを作成する場合（標準Git）

```bash
cd <ZIPを展開したディレクトリ>/Email-Verification-System
git init
git status
cat .gitignore
git add .
git status
git commit -m "Implement signed URL email verification"
git branch -M main
git remote add origin https://github.com/<ユーザー名>/Email-Verification-System.git
git push -u origin main
```

先にGitHub上で `Email-Verification-System` という**空の公開リポジトリ**を作成してください。課題の提出条件と個人の公開方針を確認して公開設定を選択してください。GitHubへの送信時はHTTPSの場合、GitHub CLI・Credential Manager・トークン等の認証が必要です。Gitパスワードによる通常のHTTPS認証は使えません。

### GitHub CLIを使用する場合

`gh auth login` でログインし、プロジェクト直下で以下を実行します。既に `origin` が存在している場合は重複登録しないでください。

```bash
gh auth login
gh repo create Email-Verification-System --public --source=. --remote=origin --push
```

`git remote -v` で既存のremoteを確認できます。既存リポジトリにアップロードする場合は、新規作成を省き、適切な既存remoteとブランチを確認して`git add`・`git commit`・`git push`します。

SSHの場合のremote例は `git@github.com:<ユーザー名>/Email-Verification-System.git` です。SSH鍵の登録が必要です。HTTPSでの認証エラーは`gh auth status`、SSHでは`ssh -T git@github.com`で確認できます。`non-fast-forward`はリモートブランチの変更確認・取り込み後に再pushしてください。秘密ファイルが`git status`に現れた場合、pushする前に`.gitignore`を再確認してください。

GitHubのWeb画面でREADME、ソース、learningフォルダーを確認し、リポジトリのURLを課題サイトへ提出します。**GitHubへのpushは自動では実施していません。**

## セキュリティ上の留意点

- URLはHMAC-SHA256で改竄を防ぎ、`hash_equals` で署名を比較します。期限は厳密に30分で、期限に達した時点で無効です。
- DBの登録メールアドレスのSHA-256値とURLの`user`を照合し、メール変更後の古いリンクを無効にします。DB更新はトランザクション内で行います。
- フォームにCSRFトークンを含め、パスワードは`password_hash`で保存します。セッションIDは登録・ログイン時に再生成します。
- 送信回数制限はSQLiteの条件付きUPDATEで行い、同一アカウントの複数セッションにも適用されます。
- 本アプリは学習用の参照実装です。外部公開する場合はHTTPS、SMTPと送信元ドメイン設定、CSRF/セッション運用、ログ管理、認証失敗回数制限、監視、バックアップなどを追加で評価してください。特にログメール送信を本番環境では禁止しています。
- リバースプロキシの後段でPHPを使う場合は、信頼できるWebサーバー側でHTTPSの状態を正しくPHPに渡してください。任意の`X-Forwarded-Proto`を本実装では信用しません。

## 学習資料

`learning/00_START_HERE.md` → `01_ARCHITECTURE.md` → `02_KEY_CONCEPTS.md` → `03_EXECUTION_FLOW.md` → `code-walkthrough/` → `04_DEBUGGING.md` → `05_REVIEW.md` の順で閲覧します。主なコードの**ソース全文**を同梱し、各ファイルについて処理順序・引数・戻り値・設計意図を説明しています。
