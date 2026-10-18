# テスト実施報告

## 実行環境

- 作成・検証環境：PHP CLI **8.4.24**（Debian）、Windows/WSL2での検証は未実施
- PHPの`pdo_sqlite`拡張：**未インストール**（このコンテナで利用不可）
- ComposerとPHPMailer：**未インストール**（このコンテナで利用不可）
- データベース本体：SQLスキーマとPDOリポジトリを作成。PDO経由の実DB試験は未実施

## 実施した検証

| 項目 | 方法 | 結果 |
|---|---|---|
| SQLite SQL（エンジン単体） | `python3 tests/check_schema_sql.py`（PHPソースから6本のSQLを抽出して実行） | 成功。INSERT/SELECT/UNIQUE/再送制限/メール変更/更新・COMMIT |
| 学習教材とソースの一致 | `python3 scripts/validate-walkthrough.py` | 23ファイル完全一致 |
| PHPファイル構文 | `find ... -name '*.php' ... php -l` | 成功（全ファイル） |
| コア機能・HTTP制御 | `php tests/run.php`（FakeUserRepositoryとFakeMailer） | **50アサーションすべて成功** |
| URL署名 | HMAC-SHA256正常値・ID/期限/ハッシュ/署名改竄・余分なパラメーター | 成功 |
| メール認証 | 30分の期限境界、正当な署名、DBフラグ相当の状態更新、二重クリック | 成功（フェイクDB） |
| メール変更 | 旧リンク無効化、新宛先に再送、再送60秒制限 | 成功（フェイクDB/メール） |
| 登録・ログイン | 不正メール、短いパスワード、CSRF不正、ログイン成功/失敗 | 成功（フェイクDB） |
| アクセス制御 | 未認証ユーザー・未ログインユーザーを制限、認証後の閲覧 | 成功（HTTP Responseの単体テスト） |
| HTMLエスケープ | 画面表示におけるHTML特殊文字のエスケープ | 成功 |

## 実施していない検証

| 未検証項目 | 理由 | 再現手順 |
|---|---|---|
| SQLite PDO接続、SQL `UPDATE` の実データ更新 | `pdo_sqlite`がないため（PythonのSQLite SQL検査は成功） | WSL Ubuntuで`php-sqlite3`を導入し`php scripts/setup.php` → ブラウザで登録/認証 |
| PHPMailerでのSMTP送信と受信 | Composer・外部SMTP設定がないため | `composer install`、`.env`のSMTP設定後に登録・受信確認 |
| ブラウザ実動作 | この環境ではDB拡張がないため、HTTPアプリの起動試験が成立しない | 開発サーバー起動後に各画面を操作 |
| リバースプロキシ/HTTPS公開 | 外部サーバーを使用していないため | 本番Webサーバー、TLS、セッション設定で確認 |
| 元のcompart-partsとの連携 | 元コードが提供されていないため | 元アプリのユーザーテーブル・認証ルーターへ適用 |
| GitHubへのpush | 認証済み操作を行っていないため | READMEに従いユーザーのアカウントで実施 |

**注意**：単体テストでのFakeRepository成功を、実DBでの統合試験成功や本番セキュリティ保証として扱わないでください。
