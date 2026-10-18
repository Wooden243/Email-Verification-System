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
