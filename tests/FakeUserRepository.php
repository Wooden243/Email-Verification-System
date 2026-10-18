<?php
declare(strict_types=1);

namespace Tests;

use App\Persistence\UserRepository;
use App\Security\SignedUrl;
use PDOException;

final class FakeUserRepository implements UserRepository
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['email'] === strtolower(trim($email))) {
                return $row;
            }
        }
        return null;
    }

    public function create(string $email, string $passwordHash): int
    {
        if ($this->findByEmail($email) !== null) {
            throw new PDOException('UNIQUE constraint failed: users.email');
        }
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id, 'email' => strtolower(trim($email)), 'password_hash' => $passwordHash,
            'email_verified' => 0, 'last_verification_sent_at' => null,
        ];
        return $id;
    }

    public function markVerifiedIfEmailMatches(int $id, string $emailDigest): bool
    {
        if (!isset($this->rows[$id]) || !hash_equals(SignedUrl::emailDigest($this->rows[$id]['email']), $emailDigest)) {
            return false;
        }
        $this->rows[$id]['email_verified'] = 1;
        return true;
    }

    public function changeEmailAndUnverify(int $id, string $email): bool
    {
        if (!isset($this->rows[$id])) {
            return false;
        }
        $existing = $this->findByEmail($email);
        if ($existing !== null && $existing['id'] !== $id) {
            throw new PDOException('UNIQUE constraint failed: users.email');
        }
        $this->rows[$id]['email'] = strtolower(trim($email));
        $this->rows[$id]['email_verified'] = 0;
        return true;
    }

    public function claimSendSlot(int $id, int $now, int $cooldownSeconds): bool
    {
        if (!isset($this->rows[$id])) {
            return false;
        }
        $last = $this->rows[$id]['last_verification_sent_at'];
        if ($last !== null && $last > $now - $cooldownSeconds) {
            return false;
        }
        $this->rows[$id]['last_verification_sent_at'] = $now;
        return true;
    }
}
