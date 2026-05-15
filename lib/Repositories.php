<?php
declare(strict_types=1);

final class UserRepository
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listAll(): array
    {
        return Database::pdo()->query('SELECT id, display_name, email, role, created_at FROM users ORDER BY role, display_name')->fetchAll();
    }
