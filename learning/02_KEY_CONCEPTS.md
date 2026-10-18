# 02 — 重要概念・構文

各概念は、定義・用途・本課題での必要性・参照箇所・例・誤解・関係を対応させています。

## 【必須】HTTPのGET、POST、ルーティング

- **意味**：GETはリソース取得の要求、POSTはフォーム等によるデータ送信を表すHTTPメソッド。ルーティングはURLのパスとメソッドを処理へ対応させる仕組み。
- **役割・必要性**：メール中のリンクをクリックするとGET、アカウント登録・再送ボタンはPOSTが起こる。
- **実装箇所**：`src/App.php::handle()`の`$route = $request->method . ' ' . $request->path`と`switch`。
- **例**：`case 'GET /verify/email': return $this->verify($request);`
- **誤解**：GETだから内容が安全とは限らない。署名リンクへのGETはDB更新の契機となるが、メールセキュリティ製品の自動リンクアクセスにも注意が必要。外部サービスではワンクリック確定の是非を設計判断する。
- **関連**：ブラウザの302/303リダイレクト、フォーム、CSRF。

## 【必須】SHA-256ハッシュとHMAC-SHA256署名

- **意味**：SHA-256は入力から固定長ダイジェストを作るハッシュ関数。HMACは共有秘密鍵を使ってメッセージの改竄を検知する認証コード。
- **役割・必要性**：URLの`user`にメールのハッシュを使い、`signature`にHMACを使って`id`・`user`・`expiration`の変更を検出する。
- **実装箇所**：`src/Security/SignedUrl.php`。
- **例**：`hash('sha256', strtolower(trim($email)))`、`hash_hmac('sha256', $message, $key)`。
- **誤解**：ハッシュ化したメールアドレスは匿名化された安全な秘密ではない。候補メールを総当たりすれば一致を調べられる場合がある。HMACを付けたからといってメールハッシュが不可逆な匿名識別子になるわけではない。
- **関連**：`APP_KEY`、`hash_equals`、署名対象の文字列の正規化。

## 【必須】署名付きURLと有効期限

- **意味**：サーバーで作成したURLのクエリパラメーターを、秘密鍵に基づく署名で保護する方式。
- **役割・必要性**：有効期限や宛先情報を改竄して不正に認証する行為を拒否する。
- **実装箇所**：`SignedUrl::parameters`/`make`/`validSignature`、`ValidateExpiration`。
- **例**：`'expiration' => (string) ($now + 1800)`、検証条件`(int) $expiration > $now`。
- **誤解**：期限検証を署名より前に行っても暗号の安全性は直接破壊しないが、両方に成功しなければ認証してはいけない。今回の実装は署名→期限→現在のDB登録メールの順で検証する。
- **関連**：リプレイ、電子メールの漏洩、メール変更による旧リンク失効。

## 【必須】DB、トランザクション、プリペアドステートメント

- **意味**：永続化したユーザー状態を読み書きする。トランザクションは一連の操作を一貫した単位として完了またはロールバックする仕組み。`prepare()`は値をSQL文字列に直接連結せず変数を束縛する。
- **役割・必要性**：未認証者の`email_verified=0`を、条件を確認して`1`へ変更する。送信時刻を原子的に更新して過剰な再送を抑える。
- **実装箇所**：`SqliteUserRepository::markVerifiedIfEmailMatches`/`claimSendSlot`。
- **例**：`$stmt = $db->prepare('UPDATE users SET email_verified = 1 WHERE id = :id');`。
- **誤解**：フォームの入力値をSQL文字列に埋め込むとSQLインジェクションが生じ得る。本課題ではバインドパラメーターを使用する。
- **関連**：SQLiteの排他ロック、`BEGIN IMMEDIATE`、UNIQUE制約。

## 【重要】セッションとCSRF

- **意味**：セッションはサーバー側のログイン状態を識別する仕組み。CSRFトークンは意図しない別サイト経由のフォーム送信を防ぐ一要素。
- **役割・必要性**：`$_SESSION['user_id']`からユーザーを特定し、再送ページを認証済みセッションに限定する。
- **実装箇所**：`App::login`, `App::currentUser`, `Security/Csrf.php`。
- **例**：`hash_equals($_SESSION['csrf_token'], $value)`。
- **誤解**：セッションがあるだけではCSRFは防止できない。またCSRFは認証リンクの暗号署名とは役割が別。
- **関連**：HttpOnly Cookie、SameSite、`session_regenerate_id(true)`。

## 【重要】ミドルウェアと依存性注入

- **意味**：ミドルウェアは本処理の前に特定の共通条件をチェックする仕組み。依存性注入は必要なモジュールを外部から渡す設計方法。
- **役割・必要性**：認証に署名・期限判定を組み込み、閲覧には確認済みアカウントを要求する。Mailerの実SMTP実装とテスト用フェイクを差し替える。
- **実装箇所**：`src/Http/Middleware/`, `VerificationService::__construct`, `Mailer`/`UserRepository`。
- **例**：`new VerificationService($users, $mailer, $urls)`。
- **誤解**：すべてを汎用ミドルウェア基盤に載せる必要はない。小規模課題なので簡単な`passes()`/`check()`と明示的な呼び出しを採用する。
- **関連**：単体テスト、関心の分離、インターフェース。

## 【重要】SMTPとPHPMailer

- **意味**：SMTPは電子メールの配送プロトコル。PHPMailerはPHPからSMTPを使用してメールを組み立て送るライブラリ。
- **役割・必要性**：URLを発行するだけでは受信者に届かないため、`PhpMailerAdapter`から送信する。
- **実装箇所**：`src/Mail/PhpMailerAdapter.php`、`MailerFactory.php`。
- **例**：`$mailer->isSMTP(); $mailer->addAddress($email); $mailer->send();`
- **誤解**：`LogMailer`の成功は実際のSMTP配送を意味しない。メール到達性には送信元の設定・受信側の判定も影響する。
- **関連**：Composer、`.env`、秘密情報の保護。

## 【参考】戻り値、例外、クロージャ

- **戻り値**：`return`は呼び出し側に値を戻して関数を終了する。例：`VerificationService::verify()`は`verified`や`expired`を返す。
- **例外**：`throw`された異常は`catch`されるまで伝播する。SMTP障害はログへ記録し、利用者には安全な一般メッセージを表示する。
- **クロージャ**：`static fn (): int => time()`は「現在時刻を返す関数」を引数として渡すもの。テストでは固定時刻に差し替え、30分境界を再現する。
- **関連**：クリーンアーキテクチャの入門的な責務分離、テスト可能性。
