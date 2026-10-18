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
