# src/Security/SignedUrl.php — 署名付きURLの生成と検証

> 出典: プロジェクト内の `src/Security/SignedUrl.php`。SHA-256: `8c8f1a1b058f65d9a55d07b803143918f531e7d5b8f75f2416919468a2fc0622`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：ID・メールハッシュ・30分後の期限からHMAC-SHA256を生成し、RFC3986準拠でURLを組み立てる。
- **入力**：id, email, now, key, baseUrl
- **出力・副作用**：クエリ配列、検証URL、署名の真偽
- **他ファイルとの関係**：VerificationService.php
- **前提知識**：SHA-256、HMAC、正規化、hash_equals

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Security;

final class SignedUrl
{
    public function __construct(private readonly string $key, private readonly string $baseUrl)
    {
        if ($key === '') {
            throw new \InvalidArgumentException('A signing key is required.');
        }
        if (!preg_match('~^https?://[^/?#]+$~D', rtrim($baseUrl, '/'))) {
            throw new \InvalidArgumentException('APP_URL must be an HTTP(S) origin without a path.');
        }
    }

    public static function emailDigest(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** @return array{id:string,user:string,expiration:string,signature:string} */
    public function parameters(int $id, string $email, int $now): array
    {
        $params = [
            'id' => (string) $id,
            'user' => self::emailDigest($email),
            'expiration' => (string) ($now + 1800),
        ];
        $params['signature'] = $this->signature($params);
        return $params;
    }

    public function make(int $id, string $email, int $now): string
    {
        return rtrim($this->baseUrl, '/') . '/verify/email?'
            . http_build_query($this->parameters($id, $email, $now), '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $query */
    public function validSignature(array $query): bool
    {
        if (count($query) !== 4) {
            return false;
        }
        foreach (['id', 'user', 'expiration', 'signature'] as $name) {
            if (!isset($query[$name]) || !is_string($query[$name])) {
                return false;
            }
        }
        if (!preg_match('/^[1-9][0-9]*$/D', $query['id'])
            || !preg_match('/^[0-9]{10,11}$/D', $query['expiration'])
            || !preg_match('/^[a-f0-9]{64}$/D', $query['user'])
            || !preg_match('/^[a-f0-9]{64}$/D', $query['signature'])) {
            return false;
        }
        $params = ['id' => $query['id'], 'user' => $query['user'], 'expiration' => $query['expiration']];
        return hash_equals($this->signature($params), $query['signature']);
    }

    /** @param array{id:string,user:string,expiration:string} $params */
    private function signature(array $params): string
    {
        $message = '/verify/email?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return hash_hmac('sha256', $message, $this->key);
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L11–L15 | ホストURLや署名のフィールド形式を正規表現で検証する。 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L16–L20 | メールをSHA-256ダイジェストへ変換する。これは署名とは別の操作。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L21–L25 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L26–L30 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L31–L35 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L36–L40 | パラメーターを決めた順序でRFC3986のクエリ文字列に変換する。順序が変わるとHMACも変わる。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L41–L45 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L46–L50 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L51–L55 | ホストURLや署名のフィールド形式を正規表現で検証する。 |
| L56–L60 | 時間差による漏洩を抑制する比較関数。比較対象の文字列の扱いに注目。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L61–L65 | パラメーターを決めた順序でRFC3986のクエリ文字列に変換する。順序が変わるとHMACも変わる。 |
| L66–L68 | HMACを生成する。鍵はサーバー内だけに保管し、URLから復元できない。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L8–L17)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`emailDigest()` (L18–L23)**：メールアドレスの余分な空白と大文字小文字を正規化してSHA-256の64文字16進数へ変える。元メールの秘匿化保証ではない。
- **`parameters()` (L24–L34)**：署名対象のid/user/expirationを固定順で作成し、HMAC署名を追加する。nowから1800秒後が期限となる。
- **`make()` (L35–L41)**：RFC3986でクエリを組み立て、APP_URLと/verify/emailへ連結してメールに載せるURLを返す。
- **`validSignature()` (L42–L62)**：4個のフィールド、型、書式、HMAC値の一致を段階的に検証する。署名はhash_equalsで比較する。
- **`signature()` (L63–L68)**：署名対象にはURLのパスとid/user/expirationを含める。signature自身は含めず、秘密鍵とHMACで認証コードを計算する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：`id=7, email=alice@example.com, now=1790000000`なら`expiration=1790001800`。`user`は正規化したメールのSHA-256。`signature`を作った後で`id=8`に変えると`hash_equals`がfalseとなる。

## ⑤ 最重要部分の再確認

1. **責務**：ID・メールハッシュ・30分後の期限からHMAC-SHA256を生成し、RFC3986準拠でURLを組み立てる。
2. **入力**：id, email, now, key, baseUrl。
3. **出力**：クエリ配列、検証URL、署名の真偽。
4. **依存先**：VerificationService.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
