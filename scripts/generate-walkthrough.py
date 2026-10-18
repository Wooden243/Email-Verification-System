#!/usr/bin/env python3
"""Regenerate Japanese line/block walkthroughs with exact, SHA-256-checked source listings."""
from pathlib import Path
from hashlib import sha256
import re

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'learning' / 'code-walkthrough'
OUT.mkdir(exist_ok=True, parents=True)

FILE_INFO = {
 'public/index.php': ('HTTPエントリーポイント', 'Webサーバーから受け取ったリクエストをアプリへ渡し、DB・メール送信を組み立てる。', 'HTTP環境変数、セッションCookie、.env', 'HTTPレスポンス', 'Config, Database, SqliteUserRepository, SignedUrl, VerificationService, App', 'PHPのリクエストライフサイクル・セッション'),
 'public/router.php': ('組み込みWebサーバーのルーター', 'CSSなどの静的ファイルはサーバーへ返し、他はPHPフロントコントローラへ渡す。', 'REQUEST_URI', 'public/index.phpへの委譲', 'public/index.php', 'ドキュメントルートとパス解決'),
 'src/bootstrap.php': ('依存クラスと設定の読み込み', 'PSR-4に準じてsrc/内のApp名前空間のクラスを読込み、任意のComposer autoloadを導入する。', 'クラス名と.envファイル', 'オートロードとConfig初期化', 'Config.php, vendor/autoload.php', '名前空間、autoload'),
 'src/Config.php': ('設定と秘密鍵の管理', '環境ファイルを解析し、検証済みの署名鍵をデコードして提供する。', '.envと環境変数', '設定文字列・秘密鍵のバイト列', 'bootstrap.php, SignedUrl.php', '.env、hex2bin、静的メソッド'),
 'src/App.php': ('HTTPルーティングとアプリ制御', '登録・ログイン・ログアウト・認証URL・再送・保護ページをURL/HTTPメソッドごとに処理する。', 'Request、$_SESSION', 'Response、登録/認証/送信の状態変化', 'UserRepository, VerificationService, View, Csrf, RequireVerified', 'GET/POST、Cookieセッション、リダイレクト、例外'),
 'src/View.php': ('HTML生成', 'エスケープしたHTML画面とCSRF付きフォームを組み立てる。', '画面名、ユーザー情報', 'HTML文字列', 'App, Csrf', 'HTMLエスケープ、フォーム、XSS'),
 'src/Http/Request.php': ('HTTPリクエストの表現', 'PHPのスーパーグローバルからmethod/path/query/formを抽出する。', '$_SERVER, $_GET, $_POST', 'Requestオブジェクト', 'App.php', 'HTTPリクエストと値オブジェクト'),
 'src/Http/Response.php': ('HTTPレスポンスの表現', 'HTTP状態コード、ヘッダー、HTML本文をまとめる。', 'status, body, headers', 'HTTP送信、303リダイレクト', 'App.php', 'HTTP状態コードとLocation'),
 'src/Http/Middleware/ValidateSignature.php': ('署名検証ミドルウェア', 'クエリの署名がHMACで生成した正しい署名かを判定する。', 'query', 'bool', 'SignedUrl::validSignature', 'ミドルウェア・真正性'),
 'src/Http/Middleware/ValidateExpiration.php': ('期限ミドルウェア', 'expirationが数値で、現在時刻より未来か確認する。', 'query, now', 'bool', 'VerificationService::verify', 'Unix時間・厳密比較'),
 'src/Http/Middleware/RequireVerified.php': ('認証済みページの保護', '未ログインならログインへ、未認証なら再送へ、それ以外は通す。', 'DBから得たユーザー情報', 'Responseまたはnull', 'App::handle', '認可・分岐'),
 'src/Security/Csrf.php': ('CSRF対策', 'セッションにランダムなトークンを保持し、フォーム送信時に比較する。', '$_SESSION、フォームのcsrf', 'トークンまたは真偽', 'App.php, View.php', 'CSRFとsession'),
 'src/Security/SignedUrl.php': ('署名付きURLの生成と検証', 'ID・メールハッシュ・30分後の期限からHMAC-SHA256を生成し、RFC3986準拠でURLを組み立てる。', 'id, email, now, key, baseUrl', 'クエリ配列、検証URL、署名の真偽', 'VerificationService.php', 'SHA-256、HMAC、正規化、hash_equals'),
 'src/Persistence/UserRepository.php': ('永続化境界のインターフェース', 'ビジネスロジックがSQLiteという実装詳細に直接依存しないよう契約を定義する。', 'ID、メール、ハッシュ、日時', 'ユーザーデータ、ID、成功/失敗', 'SqliteUserRepository, FakeUserRepository', 'interface、抽象化、依存性注入'),
 'src/Persistence/SqliteUserRepository.php': ('SQLiteユーザーリポジトリ', 'ユーザーCRUD、メールとハッシュ照合、署名成功時の更新、送信間隔をSQLで扱う。', 'PDO、ID、メール、ダイジェスト', 'ユーザーレコード、bool、ID', 'UserRepository, SignedUrl, schema.sql', 'PDO、プリペアドステートメント、トランザクション'),
 'src/Persistence/Database.php': ('データベース接続', 'SQLite拡張の有無・DBファイルを確認し、安全なPDO接続を返す。', 'SQLiteファイルパス', 'PDO', 'public/index.php', 'PHP拡張とPDO'),
 'src/Mail/Mailer.php': ('メール配信の抽象インターフェース', '検証メールの送信契約を定義し、外部SMTPと開発ログを差し替え可能にする。', '宛先メールアドレス、署名付きURL', 'void（失敗は例外）', 'LogMailer, PhpMailerAdapter, VerificationService', 'interface、依存性逆転'),
 'src/Mail/LogMailer.php': ('開発メールのファイル記録', '実メールは送らず、URLと宛先を非公開ログに残す。', '送信先、URL、ファイルパス', 'dev-mails.logへの追記', 'MailerFactory.php', 'ファイルI/O、LOCK_EX、chmod'),
 'src/Mail/PhpMailerAdapter.php': ('PHPMailerによる実メール送信', 'SMTPの接続・認証・送信元/宛先・本文を設定し、認証URLを実メールとして送る。', 'SMTP設定、宛先、URL', 'SMTP送信（失敗時は例外）', 'Composer, PHPMailer, MailerFactory', 'SMTP、外部ライブラリ、TLS'),
 'src/Mail/MailerFactory.php': ('メール配信手段の選択', '開発時のログとSMTP送信を環境変数で切り替え、本番でのログ方式を禁止する。', 'APP_ENV、MAIL_TRANSPORT', 'Mailerオブジェクト', 'LogMailer, PhpMailerAdapter', 'Factoryパターン、環境変数'),
 'src/Service/VerificationService.php': ('認証の中心サービス', '登録/再送の署名生成と送信、署名・期限・現登録メールの検証を統合する。', 'ユーザーID、now、query', '送信のbool、検証結果文字列', 'UserRepository, Mailer, SignedUrl, ValidateSignature, ValidateExpiration', 'サービス層・メール送信・検証フロー'),
 'scripts/setup.php': ('初期セットアップ', '鍵生成とDBスキーマの作成を行う。再実行しても.envの既存鍵は保持する。', '.env.example, schema.sql', '.envとstorage/app.sqlite', 'Config, PDO', 'セットアップ、秘匿情報、DB初期化'),
 'scripts/show-last-link.php': ('開発メールのURL表示', '開発ログの最後のレコードから認証URLを読み出す。', 'dev-mails.log', '標準出力に検証URL', 'Config, LogMailer', 'CLI、JSONログ、秘密情報'),
}

METHODS = {
 'load': '環境設定ファイルの行を読み、キーの形式を制限し、設定値を保持する。戻り値はなくConfig内の静的状態が変わる。',
 'get': 'getenvを優先して設定値を文字列として返す。OSの環境変数で.envを上書きできる。',
 'signingKey': 'APP_KEYを64桁の16進数として検証し、hex2binで32バイトのHMAC鍵に変換する。',
 'emailDigest': 'メールアドレスの余分な空白と大文字小文字を正規化してSHA-256の64文字16進数へ変える。元メールの秘匿化保証ではない。',
 'parameters': '署名対象のid/user/expirationを固定順で作成し、HMAC署名を追加する。nowから1800秒後が期限となる。',
 'make': 'RFC3986でクエリを組み立て、APP_URLと/verify/emailへ連結してメールに載せるURLを返す。',
 'validSignature': '4個のフィールド、型、書式、HMAC値の一致を段階的に検証する。署名はhash_equalsで比較する。',
 'signature': '署名対象にはURLのパスとid/user/expirationを含める。signature自身は含めず、秘密鍵とHMACで認証コードを計算する。',
 'sendVerificationEmail': 'DB上の未認証ユーザーを探し、60秒の送信枠を取得し、署名付きURLをメール配信先へ渡す。失敗時の例外を握りつぶさない。',
 'verify': '署名検証→期限検証→現在メール照合とDB更新の順番で実行する。返り値はinvalid/expired/mismatch/verified。',
 'findById': 'idをプレースホルダーへ束縛してユーザー1件を取得し、存在しなければnullを返す。',
 'findByEmail': 'メールを正規化して検索し、該当ユーザーまたはnullを返す。',
 'create': '未認証の新規アカウントをDBへINSERTし、生成されたユーザーIDを返す。',
 'markVerifiedIfEmailMatches': 'BEGIN IMMEDIATEで排他を確保し、現在のDBメールとURLのハッシュが一致する場合だけemail_verifiedを1へ変更する。',
 'changeEmailAndUnverify': 'ユーザーの登録メールを置き換え、email_verifiedを0に戻す。再送時刻を保持することで送信制限を回避させない。',
 'claimSendSlot': '前回送信が指定間隔以前の場合のみlast_verification_sent_atを更新する。条件付きUPDATEのrowCountで成功を返す。',
 'connect': 'pdo_sqlite拡張とDBの存在を確認してPDOを生成し、例外/フェッチ等のオプションを設定する。',
 'token': 'トークンが無ければ32バイトの乱数から64桁の16進文字列を作成し、セッションに保存する。',
 'valid': 'フォームから送られたCSRF値が文字列かを確認し、セッションのトークンと定数時間で比較する。',
 'handle': 'POST全体のCSRFを先に検証してから、HTTPメソッドとパスを組み合わせた文字列で登録・ログイン・検証・保護画面へ分岐する。',
 'register': 'メールとパスワードを検査し、hash付きでユーザーを作成。セッション再生成後に認証メールを送信して再送画面へ転送する。',
 'login': 'メールとパスワードハッシュを照合し、ログイン後にemail_verifiedの値で遷移先を選ぶ。',
 'resend': '認証前ユーザーに限定し、入力メールの変更を反映してから発行・送信する。再送制限や送信エラーを安全なメッセージで通知する。',
 'currentUser': 'セッションのuser_idを検査し、DBから現在のユーザー情報を取得する。ログインしていないときはnull。',
 'normalizeEmail': 'POST入力が文字列であることを確認し、空白と大文字小文字を正規化してメール書式・最大長を検査する。',
 'flash': '一度きりの通知文を$_SESSIONに格納する。画面を表示する際にView::pageで読み取り後削除される。',
 'error': '任意のエラーテキストをHTMLエスケープし、指定したHTTP状態コードを含む画面を返す。',
 'page': '画面生成View::pageをResponseオブジェクトで包み、HTTP送信とは分離する。',
 'escape': 'htmlspecialcharsで&、引用符、タグの特殊文字をエスケープしてXSSを防ぐ。',
 'dashboard': '認証成功ユーザーのメールアドレスをエスケープして保護ページを組み立てる。',
 'check': 'nullならログインへ、未認証なら再送へ303で転送。認証済みならnullで処理続行を許可する。',
 'passes': '単一条件をboolで返す。VerificationService内のチェック処理を分離している。',
 'fromGlobals': '$_SERVER、$_GET、$_POSTを読み、解析済みパスをRequestのプロパティへ入れる。',
 'redirect': '内部パスだけを許可してLocationヘッダー付き303レスポンスを返す。外部URLによるオープンリダイレクトを防ぐ。',
 'send': 'status/headers/bodyをPHPのHTTP出力関数へ渡す。実際にレスポンスが利用者へ流れる境界。',
 '__construct': '必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。',
}

RULES = [
 ('hash_hmac', 'HMACを生成する。鍵はサーバー内だけに保管し、URLから復元できない。'),
 ('hash_equals', '時間差による漏洩を抑制する比較関数。比較対象の文字列の扱いに注目。'),
 ('hash(\'sha256\'', 'メールをSHA-256ダイジェストへ変換する。これは署名とは別の操作。'),
 ('http_build_query', 'パラメーターを決めた順序でRFC3986のクエリ文字列に変換する。順序が変わるとHMACも変わる。'),
 ('return Response::redirect', '処理を終了してHTTP 303による移動指示を返す。'),
 ('Response::redirect', '302ではなく303を使い、POST後の再取得をGETにする。'),
 ('password_hash', 'パスワードを平文ではなくハッシュとして保存する。'),
 ('password_verify', '保存されたパスワードハッシュと入力されたパスワードの一致を検査。'),
 ('session_regenerate_id', 'ログイン・登録による権限変更時にセッション固定攻撃のリスクを抑える。'),
 ('$_SESSION', 'セッションに結びついたユーザー状態またはCSRFトークンを操作する。'),
 ('->prepare(', 'SQLで値を直接連結せずプレースホルダーを使う。'),
 ('->execute(', 'バインド値を渡してSQLを実行する。'),
 ('BEGIN IMMEDIATE', '書込み予約ロックをトランザクション開始時に確保する。'),
 ('->commit(', '処理が成功したトランザクションを確定する。'),
 ('->rollBack(', '例外時にデータを更新前の状態へ戻す。'),
 ('->rowCount(', '条件付きUPDATEが成功したかを更新行数で判定する。'),
 ('->sendVerificationEmail(', '宛先と署名付きURLをMailerの実装へ渡す。'),
 ('->make(', '発行時刻を使って30分間の署名付きリンクを構築する。'),
 ('->validSignature(', 'HMAC署名の正当性を確認し、不正クエリを拒否する。'),
 ('->markVerifiedIfEmailMatches(', '現メールとの一致を満たす場合だけ認証状態を更新する。'),
 ('->claimSendSlot(', '再送を許可してよいかDBの条件付き更新で判定する。'),
 ('->changeEmailAndUnverify(', '宛先変更をDBへ反映し、認証フラグを未確認に戻す。'),
 ('->findBy', 'リポジトリ経由で現在のユーザー情報を読み取る。'),
 ('ctype_digit(', '有効期限文字列が十進数字のみであることを確認する。'),
 ('preg_match(', 'ホストURLや署名のフィールド形式を正規表現で検証する。'),
 ('filter_var(', 'サーバー側でメールアドレス書式を検査する。'),
 ('htmlspecialchars(', '画面へ出す文字列をエスケープしHTML注入を防ぐ。'),
 ('$_GET', 'URLクエリの入力。信頼せず検証してから使う。'),
 ('$_POST', 'フォーム入力。クライアント側の入力制約だけに依存しない。'),
 ('http_response_code(', 'HTTPの状態コードを設定する。'),
 ('header(', 'ブラウザに送るHTTPヘッダーを設定する。'),
 ('error_log(', '内部エラーをサーバーログに記録する。画面へ秘密情報を出さない。'),
 ('file_put_contents(', 'ファイルへ排他付き追記または設定作成を行う。'),
 ('random_bytes(', '予測困難な暗号学的乱数を生成する。'),
 ('PHPMailer(', 'PHPMailerオブジェクトを生成してSMTPで送信する。'),
 ('->isSMTP(', 'PHP標準mail関数ではなくSMTPモードを指定する。'),
 ('->addAddress(', '検証メールの送信先を指定する。'),
 ('->send(', '実際のメール配送要求を行う。送達完了とは別。'),
 ('switch (', 'HTTPメソッドとURLによるルート分岐が始まる。'),
 ('case \'', 'このHTTPルートに対応する処理を開始する。'),
 ('catch (', '例外を捕捉し、失敗状態を処理する。'),
 ('throw new ', '不正状態や外部障害を例外として呼び出し元へ伝える。'),
 ('return ', 'この行で関数の処理を終了し、値を呼び出し元へ戻す。'),
]

def line_notes(chunk, name):
    candidate = []
    for item, note in RULES:
        if item in chunk:
            candidate.append(note)
    if candidate:
        return ' '.join(dict.fromkeys(candidate[:3]))
    if re.search(r'\b(?:if|elseif)\s*\(', chunk):
        return '条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。'
    if 'function ' in chunk:
        return 'メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。'
    if 'public readonly ' in chunk or 'private readonly ' in chunk:
        return 'プロパティの宣言。readonlyにより初期化後の参照の差し替えを防ぐ。'
    if '$this->' in chunk:
        return '保持している依存オブジェクトやプロパティを参照する。他のファイルとの接続点を確認する。'
    if '$' in chunk and '=' in chunk:
        return '変数への代入を行う。右辺で作った値を後続処理に受け渡す。'
    if re.search(r'\b(?:interface|class|namespace|use)\b', chunk):
        return '名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。'
    return '構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。'

def guide(path):
    title, purpose, inputs, outputs, rel, prereq = FILE_INFO[path]
    src = (ROOT / path).read_text(encoding='utf-8')
    digest = sha256(src.encode()).hexdigest()
    lines = src.splitlines()
    methods = []
    for i, line in enumerate(lines, 1):
        match = re.search(r'\bfunction\s+(\w+)\s*\(', line)
        if match:
            methods.append((match.group(1), i))
    entry = []
    for idx, (name, n) in enumerate(methods):
        end = (methods[idx + 1][1] - 1) if idx + 1 < len(methods) else len(lines)
        entry.append(f'- **`{name}()` (L{n}–L{end})**：{METHODS.get(name, "宣言された型・分岐・戻り値を確認し、呼出し側へどの値が流れるかを追う。") }')
    if not entry:
        entry = ['- **トップレベル処理**：Web/CLIの入口から終了まで上から順に実行。宣言以外の関数呼び出し順と副作用を確認する。']
    chunks = []
    for start in range(0, len(lines), 5):
        end = min(len(lines), start+5)
        fragment = '\n'.join(lines[start:end])
        fragments = [x.strip() for x in lines[start:end] if x.strip()]
        if not fragments:
            note = '空行・段落の区切り。処理内容は前後のコードに対応する。'
        else:
            note = line_notes(fragment, path)
            # Increase specificity for route labels, direct flow.
            route = re.search(r"case '((?:GET|POST) /[^']*)'", fragment)
            if route:
                note = f'`{route.group(1)}`を受ける分岐。必要な認証・入力確認の後、対応するResponseを返す。 ' + note
        # annotate relevant local names for observability
        chunks.append(f'| L{start+1}–L{end} | {note.replace("|", "\\|")} |')
    special = {
      'src/App.php': '例：`POST /register`で`email=alice@example.com, password=(12文字以上)`を受け取り、`create()`の戻り値が`id=7`なら`$_SESSION["user_id"]=7`となる。メール送信後は303で`/verify/resend`へ遷移する。`GET /dashboard`では`email_verified=0`なら閲覧不可。',
      'src/Security/SignedUrl.php': '例：`id=7, email=alice@example.com, now=1790000000`なら`expiration=1790001800`。`user`は正規化したメールのSHA-256。`signature`を作った後で`id=8`に変えると`hash_equals`がfalseとなる。',
      'src/Service/VerificationService.php': '例：`sendVerificationEmail(7, 1790000000)`で送信枠が取れればMailerが1回呼ばれる。`verify(query, 1790001800)`は署名が正しくても`expired`を返す。',
      'src/Persistence/SqliteUserRepository.php': '例：ID7の現メールが`alice2@example.com`へ更新され、古いURLの`user=SHA256(alice@example.com)`が渡された場合、トランザクション内で一致せず`false`を返す。',
      'src/Mail/PhpMailerAdapter.php': '例：`sendVerificationEmail("alice@example.com", "https://example.com/verify/email?...")`はSMTP認証済みサーバーへ送信要求し、失敗時は例外が送信元に伝わる。',
      'src/Http/Middleware/RequireVerified.php': '例：`$user=null`は`/login`、`["email_verified"=>0]`は`/verify/resend`、`["email_verified"=>1]`は`null`。',
    }
    trace = special.get(path, f'例：上記の入力（{inputs}）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（{outputs}）を記録する。')
    out = f'''# {path} — {title}

> 出典: プロジェクト内の `{path}`。SHA-256: `{digest}`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：{purpose}
- **入力**：{inputs}
- **出力・副作用**：{outputs}
- **他ファイルとの関係**：{rel}
- **前提知識**：{prereq}

## ② ソースコード全文（省略なし）

```php
{src.rstrip()}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
{chr(10).join(chunks)}

### メソッドと引数・戻り値の関係

{chr(10).join(entry)}

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

{trace}

## ⑤ 最重要部分の再確認

1. **責務**：{purpose}
2. **入力**：{inputs}。
3. **出力**：{outputs}。
4. **依存先**：{rel}。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
'''
    filename = path.replace('/', '--').replace('.php', '.md')
    (OUT / filename).write_text(out, encoding='utf-8')
    return filename, path

items = [guide(p) for p in FILE_INFO]
manifest = ['# 主要ソースファイルの全文・逐次解説', '', '各資料には**実装ソース全文**とSHA-256ハッシュ、処理順の行番号解説、実行トレースを掲載しています。', 'コードの変更後は`python3 scripts/generate-walkthrough.py`を再実行し、`python3 scripts/validate-walkthrough.py`で整合性を確認してください。', '', '| 解説資料 | 対象ソース |', '|---|---|']
for filename, path in items:
    manifest.append(f'| [{filename}]({filename}) | `{path}` |')
(OUT / 'README.md').write_text('\n'.join(manifest)+'\n', encoding='utf-8')
print(f'Generated {len(items)} exact-source walkthroughs.')
