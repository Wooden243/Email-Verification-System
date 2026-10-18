# src/Persistence/SqliteUserRepository.php — SQLiteユーザーリポジトリ

> 出典: プロジェクト内の `src/Persistence/SqliteUserRepository.php`。SHA-256: `aaefa9283b95599aefe2a8820b0e2bc8a86beb9f78daf3e020a38995ae6d3bac`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：ユーザーCRUD、メールとハッシュ照合、署名成功時の更新、送信間隔をSQLで扱う。
- **入力**：PDO、ID、メール、ダイジェスト
- **出力・副作用**：ユーザーレコード、bool、ID
- **他ファイルとの関係**：UserRepository, SignedUrl, schema.sql
- **前提知識**：PDO、プリペアドステートメント、トランザクション

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Persistence;

use App\Security\SignedUrl;
use PDO;

final class SqliteUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (email,password_hash,email_verified) VALUES (:email,:hash,0)');
        $stmt->execute([':email' => strtolower(trim($email)), ':hash' => $passwordHash]);
        return (int) $this->db->lastInsertId();
    }

    public function markVerifiedIfEmailMatches(int $id, string $emailDigest): bool
    {
        // Transaction prevents a concurrent email change from validating the old address.
        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $user = $this->findById($id);
            if ($user === null || !hash_equals(SignedUrl::emailDigest((string) $user['email']), $emailDigest)) {
                $this->db->exec('COMMIT');
                return false;
            }
            $stmt = $this->db->prepare('UPDATE users SET email_verified = 1 WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $this->db->exec('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function changeEmailAndUnverify(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET email = :email, email_verified = 0 WHERE id = :id');
        $stmt->execute([':email' => strtolower(trim($email)), ':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function claimSendSlot(int $id, int $now, int $cooldownSeconds): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET last_verification_sent_at = :now WHERE id = :id AND (last_verification_sent_at IS NULL OR last_verification_sent_at <= :earliest)');
        $stmt->execute([':now' => $now, ':id' => $id, ':earliest' => $now - $cooldownSeconds]);
        return $stmt->rowCount() === 1;
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | SQLで値を直接連結せずプレースホルダーを使う。 |
| L16–L20 | バインド値を渡してSQLを実行する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L21–L25 | SQLで値を直接連結せずプレースホルダーを使う。 バインド値を渡してSQLを実行する。 |
| L26–L30 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L31–L35 | パスワードを平文ではなくハッシュとして保存する。 SQLで値を直接連結せずプレースホルダーを使う。 バインド値を渡してSQLを実行する。 |
| L36–L40 | 書込み予約ロックをトランザクション開始時に確保する。 |
| L41–L45 | 時間差による漏洩を抑制する比較関数。比較対象の文字列の扱いに注目。 リポジトリ経由で現在のユーザー情報を読み取る。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L46–L50 | SQLで値を直接連結せずプレースホルダーを使う。 バインド値を渡してSQLを実行する。 例外を捕捉し、失敗状態を処理する。 |
| L51–L55 | 保持している依存オブジェクトやプロパティを参照する。他のファイルとの接続点を確認する。 |
| L56–L60 | SQLで値を直接連結せずプレースホルダーを使う。 バインド値を渡してSQLを実行する。 条件付きUPDATEが成功したかを更新行数で判定する。 |
| L61–L65 | SQLで値を直接連結せずプレースホルダーを使う。 |
| L66–L69 | バインド値を渡してSQLを実行する。 条件付きUPDATEが成功したかを更新行数で判定する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L11–L12)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`findById()` (L13–L20)**：idをプレースホルダーへ束縛してユーザー1件を取得し、存在しなければnullを返す。
- **`findByEmail()` (L21–L28)**：メールを正規化して検索し、該当ユーザーまたはnullを返す。
- **`create()` (L29–L35)**：未認証の新規アカウントをDBへINSERTし、生成されたユーザーIDを返す。
- **`markVerifiedIfEmailMatches()` (L36–L55)**：BEGIN IMMEDIATEで排他を確保し、現在のDBメールとURLのハッシュが一致する場合だけemail_verifiedを1へ変更する。
- **`changeEmailAndUnverify()` (L56–L62)**：ユーザーの登録メールを置き換え、email_verifiedを0に戻す。再送時刻を保持することで送信制限を回避させない。
- **`claimSendSlot()` (L63–L69)**：前回送信が指定間隔以前の場合のみlast_verification_sent_atを更新する。条件付きUPDATEのrowCountで成功を返す。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：ID7の現メールが`alice2@example.com`へ更新され、古いURLの`user=SHA256(alice@example.com)`が渡された場合、トランザクション内で一致せず`false`を返す。

## ⑤ 最重要部分の再確認

1. **責務**：ユーザーCRUD、メールとハッシュ照合、署名成功時の更新、送信間隔をSQLで扱う。
2. **入力**：PDO、ID、メール、ダイジェスト。
3. **出力**：ユーザーレコード、bool、ID。
4. **依存先**：UserRepository, SignedUrl, schema.sql。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
