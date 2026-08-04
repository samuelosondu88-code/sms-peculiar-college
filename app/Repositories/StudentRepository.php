<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the students table.
 */
final class StudentRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.first_name, u.last_name, u.email, u.avatar, c.name AS class_name, c.section
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByAdmissionNo(string $admissionNo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.first_name, u.last_name, u.email, u.role, u.status, c.name AS class_name, c.section
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.admission_no = ? LIMIT 1"
        );
        $stmt->execute([$admissionNo]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listByClass(int $classId, string $status = 'active'): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, u.first_name, u.last_name, s.admission_no, s.gender
             FROM students s
             JOIN users u ON s.user_id = u.id
             WHERE s.class_id = ? AND s.status = ?
             ORDER BY u.last_name, u.first_name"
        );
        $stmt->execute([$classId, $status]);
        return $stmt->fetchAll();
    }

    public function insert(int $userId, string $admissionNo, int $classId): int
    {
        $stmt = $this->db->prepare("INSERT INTO students (user_id, admission_no, class_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $admissionNo, $classId]);
        return (int) $this->db->lastInsertId();
    }
}