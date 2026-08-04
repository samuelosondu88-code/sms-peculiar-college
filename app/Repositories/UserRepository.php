<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the users table.
 */
final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (username, email, password_hash, role, first_name, last_name, phone)
             VALUES (:username, :email, :password_hash, :role, :first_name, :last_name, :phone)"
        );
        $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $id]);
    }
}