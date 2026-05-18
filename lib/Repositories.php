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

    public static function updateRole(int $id, string $role): void
    {
        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([':role' => $role, ':id' => $id]);
    }
}

final class TemplateRepository
{
    public static function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM templates ORDER BY category, name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM templates WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $payload): int
    {
        $now = date('c');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO templates (name, slug, category, description, template_html, template_css, created_at, updated_at)
             VALUES (:name, :slug, :category, :description, :template_html, :template_css, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':name' => $payload['name'],
            ':slug' => $payload['slug'],
            ':category' => $payload['category'],
            ':description' => $payload['description'],
            ':template_html' => $payload['template_html'],
            ':template_css' => $payload['template_css'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }
}

final class DocumentRepository
