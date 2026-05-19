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
{
    public static function create(array $payload): int
    {
        $now = date('c');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO documents (title, template_id, status, source_type, data_json, html_output, pdf_path, created_by, created_at, updated_at)
             VALUES (:title, :template_id, :status, :source_type, :data_json, :html_output, :pdf_path, :created_by, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':title' => $payload['title'],
            ':template_id' => $payload['template_id'],
            ':status' => $payload['status'],
            ':source_type' => $payload['source_type'],
            ':data_json' => json_encode($payload['data'], JSON_UNESCAPED_UNICODE),
            ':html_output' => $payload['html_output'],
            ':pdf_path' => $payload['pdf_path'],
            ':created_by' => $payload['created_by'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function updatePdfPath(int $id, string $pdfPath): void
    {
        $stmt = Database::pdo()->prepare('UPDATE documents SET pdf_path = :pdf_path, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':pdf_path' => $pdfPath,
            ':updated_at' => date('c'),
            ':id' => $id,
        ]);
    }

    public static function listAll(): array
    {
        $sql = 'SELECT d.id, d.title, d.status, d.source_type, d.created_at, t.name AS template_name, u.display_name AS author
                FROM documents d
                JOIN templates t ON t.id = d.template_id
                JOIN users u ON u.id = d.created_by
                ORDER BY d.created_at DESC';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, t.name AS template_name, t.template_css, u.display_name AS author
             FROM documents d
             JOIN templates t ON t.id = d.template_id
             JOIN users u ON u.id = d.created_by
             WHERE d.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $row['data'] = json_decode($row['data_json'], true) ?: [];
        return $row;
    }

    public static function recentStats(): array
    {
        $pdo = Database::pdo();
        return [
            'templates' => (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn(),
            'documents' => (int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
            'imports' => (int) $pdo->query('SELECT COUNT(*) FROM import_jobs')->fetchColumn(),
            'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        ];
    }

    public static function logImport(string $fileName, int $rowCount, string $status, string $notes, int $createdBy): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO import_jobs (file_name, row_count, status, notes, created_by, created_at)
             VALUES (:file_name, :row_count, :status, :notes, :created_by, :created_at)'
        );
        $stmt->execute([
            ':file_name' => $fileName,
            ':row_count' => $rowCount,
            ':status' => $status,
            ':notes' => $notes,
            ':created_by' => $createdBy,
            ':created_at' => date('c'),
        ]);
    }

    public static function listImports(): array
    {
        $sql = 'SELECT i.*, u.display_name AS author
                FROM import_jobs i
                JOIN users u ON u.id = i.created_by
                ORDER BY i.created_at DESC';
        return Database::pdo()->query($sql)->fetchAll();
    }
}

final class AuditRepository
{
    public static function log(string $actorEmail, string $action, string $details): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_log (actor_email, action, details, created_at)
             VALUES (:actor_email, :action, :details, :created_at)'
        );
        $stmt->execute([
            ':actor_email' => $actorEmail,
            ':action' => $action,
            ':details' => $details,
            ':created_at' => date('c'),
        ]);
    }

    public static function recent(int $limit = 30): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

