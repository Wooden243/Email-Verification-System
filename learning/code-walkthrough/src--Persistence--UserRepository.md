# src/Persistence/UserRepository.php — 永続化境界のインターフェース

> 出典: プロジェクト内の `src/Persistence/UserRepository.php`。SHA-256: `f300699d9dc5bb42ec8cece7d992105776779b1ce730b7eb7d23b4b36a9e84df`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：ビジネスロジックがSQLiteという実装詳細に直接依存しないよう契約を定義する。
- **入力**：ID、メール、ハッシュ、日時
- **出力・副作用**：ユーザーデータ、ID、成功/失敗
- **他ファイルとの関係**：SqliteUserRepository, FakeUserRepository
- **前提知識**：interface、抽象化、依存性注入

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Persistence;

interface UserRepository
{
    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array;
    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array;
    public function create(string $email, string $passwordHash): int;
    public function markVerifiedIfEmailMatches(int $id, string $emailDigest): bool;
    public function changeEmailAndUnverify(int $id, string $email): bool;
    public function claimSendSlot(int $id, int $now, int $cooldownSeconds): bool;
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L11–L15 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L16–L16 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`findById()` (L9–L10)**：idをプレースホルダーへ束縛してユーザー1件を取得し、存在しなければnullを返す。
- **`findByEmail()` (L11–L11)**：メールを正規化して検索し、該当ユーザーまたはnullを返す。
- **`create()` (L12–L12)**：未認証の新規アカウントをDBへINSERTし、生成されたユーザーIDを返す。
- **`markVerifiedIfEmailMatches()` (L13–L13)**：BEGIN IMMEDIATEで排他を確保し、現在のDBメールとURLのハッシュが一致する場合だけemail_verifiedを1へ変更する。
- **`changeEmailAndUnverify()` (L14–L14)**：ユーザーの登録メールを置き換え、email_verifiedを0に戻す。再送時刻を保持することで送信制限を回避させない。
- **`claimSendSlot()` (L15–L16)**：前回送信が指定間隔以前の場合のみlast_verification_sent_atを更新する。条件付きUPDATEのrowCountで成功を返す。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（ID、メール、ハッシュ、日時）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（ユーザーデータ、ID、成功/失敗）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：ビジネスロジックがSQLiteという実装詳細に直接依存しないよう契約を定義する。
2. **入力**：ID、メール、ハッシュ、日時。
3. **出力**：ユーザーデータ、ID、成功/失敗。
4. **依存先**：SqliteUserRepository, FakeUserRepository。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
