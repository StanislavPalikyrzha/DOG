<?php

function user_find_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_find_by_email($email)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => trim(strtolower((string) $email))]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_list_all()
{
    return db()->query('SELECT id, display_name, email, role, created_at FROM users ORDER BY role, display_name')->fetchAll();
}

function user_update_role($id, $role)
{
    if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
        throw new InvalidArgumentException('Invalid role.');
    }

    $stmt = db()->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute([':role' => $role, ':id' => (int) $id]);
}

function template_list_all()
{
    return db()->query('SELECT * FROM templates ORDER BY category, name')->fetchAll();
}

function template_find_by_id($id)
{
    $stmt = db()->prepare('SELECT * FROM templates WHERE id = :id');
    $stmt->execute([':id' => (int) $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function template_create($payload)
{
    $stmt = db()->prepare(
        'INSERT INTO templates (name, slug, category, description, template_html, template_css, created_at, updated_at)
         VALUES (:name, :slug, :category, :description, :template_html, :template_css, :created_at, :updated_at)'
    );
    $now = date('c');

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

    return (int) db()->lastInsertId();
}

function document_create($payload)
{
    $stmt = db()->prepare(
        'INSERT INTO documents (title, template_id, status, source_type, data_json, html_output, pdf_path, created_by, created_at, updated_at)
         VALUES (:title, :template_id, :status, :source_type, :data_json, :html_output, :pdf_path, :created_by, :created_at, :updated_at)'
    );
    $now = date('c');

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

    return (int) db()->lastInsertId();
}

function document_update_pdf_path($id, $pdf_path)
{
    $stmt = db()->prepare('UPDATE documents SET pdf_path = :pdf_path, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':pdf_path' => $pdf_path,
        ':updated_at' => date('c'),
        ':id' => (int) $id,
    ]);
}

function document_list_all()
{
    $sql = 'SELECT d.id, d.title, d.status, d.source_type, d.created_at, t.name AS template_name, u.display_name AS author
            FROM documents d
            JOIN templates t ON t.id = d.template_id
            JOIN users u ON u.id = d.created_by
            ORDER BY d.created_at DESC';

    return db()->query($sql)->fetchAll();
}

function document_find_by_id($id)
{
    $stmt = db()->prepare(
        'SELECT d.*, t.name AS template_name, t.template_css, u.display_name AS author
         FROM documents d
         JOIN templates t ON t.id = d.template_id
         JOIN users u ON u.id = d.created_by
         WHERE d.id = :id'
    );
    $stmt->execute([':id' => (int) $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $row['data'] = json_decode($row['data_json'], true) ?: [];

    return $row;
}

function document_recent_stats()
{
    return [
        'templates' => (int) db()->query('SELECT COUNT(*) FROM templates')->fetchColumn(),
        'documents' => (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
        'imports' => (int) db()->query('SELECT COUNT(*) FROM import_jobs')->fetchColumn(),
        'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    ];
}

function import_log_add($file_name, $row_count, $status, $notes, $created_by)
{
    $stmt = db()->prepare(
        'INSERT INTO import_jobs (file_name, row_count, status, notes, created_by, created_at)
         VALUES (:file_name, :row_count, :status, :notes, :created_by, :created_at)'
    );
    $stmt->execute([
        ':file_name' => $file_name,
        ':row_count' => (int) $row_count,
        ':status' => $status,
        ':notes' => $notes,
        ':created_by' => (int) $created_by,
        ':created_at' => date('c'),
    ]);
}

function import_list_all()
{
    $sql = 'SELECT i.*, u.display_name AS author
            FROM import_jobs i
            JOIN users u ON u.id = i.created_by
            ORDER BY i.created_at DESC';

    return db()->query($sql)->fetchAll();
}

function audit_log_add($actor_email, $action, $details)
{
    $stmt = db()->prepare(
        'INSERT INTO audit_log (actor_email, action, details, created_at)
         VALUES (:actor_email, :action, :details, :created_at)'
    );
    $stmt->execute([
        ':actor_email' => $actor_email,
        ':action' => $action,
        ':details' => $details,
        ':created_at' => date('c'),
    ]);
}

function audit_recent($limit = 30)
{
    $stmt = db()->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
