<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function segments(): array
{
    $route = trim((string) ($_GET['route'] ?? ''), '/');
    if ($route === '') {
        return [];
    }

    return array_values(array_filter(explode('/', $route), static fn ($segment) => $segment !== ''));
}

function extractPlaceholders(string $html): array
{
    preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}|{{\s*(?:upper|lower|title|currency):([a-zA-Z0-9_]+)\s*}}|{{\s*if:([a-zA-Z0-9_]+)\s*}}/u', $html, $matches);
    $values = array_merge($matches[1] ?? [], $matches[2] ?? [], $matches[3] ?? []);
    $filtered = array_values(array_unique(array_filter(array_map('trim', $values), static function ($item) {
        return $item !== '' && !in_array($item, ['else', 'endif'], true);
    })));

    return $filtered;
}

function allowedRolesForTemplate(array $template): array
{
    return normalizeCsvArrayField($template['allowed_roles'] ?? 'admin,editor,viewer');
}

function templateAllowedForUser(array $template, array $user): bool
{
    return in_array($user['role'] ?? 'viewer', allowedRolesForTemplate($template), true);
}

function userIsAdmin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function sanitizeTemplateHtml(string $html): string
{
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html;
    $html = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html) ?? $html;
    $html = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $html) ?? $html;
    $html = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/i', '', $html) ?? $html;
    $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? $html;

    return trim($html);
}

function templatePayload(array $template): array
{
    return [
        'id' => (int) $template['id'],
        'title' => $template['title'],
        'slug' => $template['slug'],
        'category' => $template['category'],
        'description' => $template['description'],
        'html_content' => $template['html_content'],
        'placeholders' => safeJsonDecode($template['placeholders_json'] ?? '[]', extractPlaceholders($template['html_content'] ?? '')),
        'allowed_roles' => allowedRolesForTemplate($template),
        'is_active' => (int) $template['is_active'],
        'created_by' => $template['created_by'] !== null ? (int) $template['created_by'] : null,
        'created_at' => $template['created_at'],
        'updated_at' => $template['updated_at'],
    ];
}

function getTemplateOrFail(int $id, array $user, bool $adminBypass = false): array
{
    $stmt = db()->prepare('SELECT * FROM templates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $template = $stmt->fetch();

    if (!$template) {
        jsonResponse([
            'success' => false,
            'message' => 'Template not found.',
        ], 404);
    }

    if ((int) $template['is_active'] !== 1 && !$adminBypass) {
        jsonResponse([
            'success' => false,
            'message' => 'This template is disabled.',
        ], 403);
    }

    if (!$adminBypass && !templateAllowedForUser($template, $user)) {
        jsonResponse([
            'success' => false,
            'message' => 'You do not have access to this template.',
        ], 403);
    }

    return $template;
}

function handleAuth(array $parts): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $parts[1] ?? 'me';

    if ($method === 'POST' && $action === 'login') {
        $data = requestData();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            jsonResponse([
                'success' => false,
                'message' => 'Username and password are required.',
            ], 422);
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            jsonResponse([
                'success' => false,
                'message' => 'This account is disabled.',
            ], 403);
        }

        $_SESSION['user_id'] = (int) $user['id'];

        jsonResponse([
            'success' => true,
            'message' => 'Signed in successfully.',
            'user' => currentUser(),
        ]);
    }

    if ($method === 'POST' && $action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        jsonResponse([
            'success' => true,
            'message' => 'Signed out successfully.',
        ]);
    }

    if ($method === 'GET' && $action === 'me') {
        jsonResponse([
            'success' => true,
            'user' => currentUser(),
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Authentication route not found.',
    ], 404);
}

function handleDashboard(): void
{
    $user = requireAuth();

    $stats = [
        'users' => 0,
        'templates' => 0,
        'documents' => 0,
        'my_documents' => 0,
    ];

    if (userIsAdmin($user)) {
        $stats['users'] = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['templates'] = (int) db()->query('SELECT COUNT(*) FROM templates WHERE is_active = 1')->fetchColumn();
        $stats['documents'] = (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();
    } else {
        $templateStmt = db()->query('SELECT allowed_roles FROM templates WHERE is_active = 1');
        foreach ($templateStmt->fetchAll() as $templateRow) {
            if (in_array($user['role'] ?? 'viewer', normalizeCsvArrayField($templateRow['allowed_roles'] ?? ''), true)) {
                $stats['templates']++;
            }
        }

        $documentStmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE created_by = :user_id');
        $documentStmt->execute([':user_id' => (int) $user['id']]);
        $stats['documents'] = (int) $documentStmt->fetchColumn();
    }

    $myStmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE created_by = :user_id');
    $myStmt->execute([':user_id' => (int) $user['id']]);
    $stats['my_documents'] = (int) $myStmt->fetchColumn();

    jsonResponse([
        'success' => true,
        'stats' => $stats,
    ]);
}

function handleUsers(): void
{
    $admin = requireAdmin();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $parts = segments();
    $id = isset($parts[1]) ? (int) $parts[1] : 0;

    if ($method === 'GET' && $id === 0) {
        $rows = db()->query('SELECT id, full_name, username, role, is_active, created_at, updated_at FROM users ORDER BY id DESC')->fetchAll();
        jsonResponse([
            'success' => true,
            'items' => $rows,
        ]);
    }

    if ($method === 'POST') {
        $data = requestData();
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = trim((string) ($data['role'] ?? 'viewer'));
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($fullName === '' || $username === '' || $password === '') {
            jsonResponse([
                'success' => false,
                'message' => 'Please fill in the name, username, and password fields.',
            ], 422);
        }

        $stmt = db()->prepare(
            'INSERT INTO users (full_name, username, password_hash, role, is_active, created_at, updated_at)
             VALUES (:full_name, :username, :password_hash, :role, :is_active, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':full_name' => $fullName,
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => in_array($role, ['admin', 'editor', 'viewer'], true) ? $role : 'viewer',
            ':is_active' => $isActive,
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'User created.',
            'created_by' => $admin['username'],
        ], 201);
    }

    if (in_array($method, ['PUT', 'PATCH'], true) && $id > 0) {
        $data = requestData();
        $stmt = db()->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 username = :username,
                 role = :role,
                 is_active = :is_active,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':full_name' => trim((string) ($data['full_name'] ?? '')),
            ':username' => trim((string) ($data['username'] ?? '')),
            ':role' => in_array(trim((string) ($data['role'] ?? 'viewer')), ['admin', 'editor', 'viewer'], true)
                ? trim((string) ($data['role'] ?? 'viewer'))
                : 'viewer',
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);

        if (!empty($data['password'])) {
            $passwordStmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $passwordStmt->execute([
                ':password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                ':id' => $id,
            ]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'User updated.',
        ]);
    }

    if ($method === 'DELETE' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        jsonResponse([
            'success' => true,
            'message' => 'User deleted.',
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Users route not found.',
    ], 404);
}

function handleTemplates(): void
{
    $user = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $parts = segments();
    $id = isset($parts[1]) ? (int) $parts[1] : 0;

    if ($method === 'GET' && $id === 0) {
        $stmt = db()->query('SELECT * FROM templates ORDER BY id DESC');
        $templates = [];
        foreach ($stmt->fetchAll() as $template) {
            if (userIsAdmin($user) || ((int) $template['is_active'] === 1 && templateAllowedForUser($template, $user))) {
                $templates[] = templatePayload($template);
            }
        }

        jsonResponse([
            'success' => true,
            'items' => $templates,
        ]);
    }

    if ($method === 'GET' && $id > 0) {
        $template = getTemplateOrFail($id, $user, userIsAdmin($user));
        jsonResponse([
            'success' => true,
            'item' => templatePayload($template),
        ]);
    }

    if ($method === 'POST') {
        requireAdmin();
        $data = requestData();
        $title = trim((string) ($data['title'] ?? ''));
        $category = trim((string) ($data['category'] ?? 'general'));
        $description = trim((string) ($data['description'] ?? ''));
        $htmlContent = sanitizeTemplateHtml(trim((string) ($data['html_content'] ?? '')));
        $slug = trim((string) ($data['slug'] ?? ''));
        $allowedRoles = normalizeCsvArrayField($data['allowed_roles'] ?? []);
        $placeholders = normalizeCsvArrayField($data['placeholders'] ?? []);

        if ($title === '' || $htmlContent === '') {
            jsonResponse([
                'success' => false,
                'message' => 'Template title and HTML content are required.',
            ], 422);
        }

        $stmt = db()->prepare(
            'INSERT INTO templates (title, slug, category, description, html_content, placeholders_json, allowed_roles, is_active, created_by, created_at, updated_at)
             VALUES (:title, :slug, :category, :description, :html_content, :placeholders_json, :allowed_roles, :is_active, :created_by, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug !== '' ? slugify($slug) : slugify($title),
            ':category' => $category,
            ':description' => $description,
            ':html_content' => $htmlContent,
            ':placeholders_json' => json_encode($placeholders !== [] ? $placeholders : extractPlaceholders($htmlContent), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':allowed_roles' => implode(',', $allowedRoles !== [] ? $allowedRoles : ['admin', 'editor', 'viewer']),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':created_by' => (int) $user['id'],
            ':created_at' => now(),
            ':updated_at' => now(),
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Template created.',
        ], 201);
    }

    if (in_array($method, ['PUT', 'PATCH'], true) && $id > 0) {
        requireAdmin();
        $data = requestData();
        $htmlContent = sanitizeTemplateHtml(trim((string) ($data['html_content'] ?? '')));
        $placeholders = normalizeCsvArrayField($data['placeholders'] ?? []);

        $stmt = db()->prepare(
            'UPDATE templates
             SET title = :title,
                 slug = :slug,
                 category = :category,
                 description = :description,
                 html_content = :html_content,
                 placeholders_json = :placeholders_json,
                 allowed_roles = :allowed_roles,
                 is_active = :is_active,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':title' => trim((string) ($data['title'] ?? '')),
            ':slug' => slugify((string) ($data['slug'] ?? $data['title'] ?? 'template')),
            ':category' => trim((string) ($data['category'] ?? 'general')),
            ':description' => trim((string) ($data['description'] ?? '')),
            ':html_content' => $htmlContent,
            ':placeholders_json' => json_encode($placeholders !== [] ? $placeholders : extractPlaceholders($htmlContent), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':allowed_roles' => implode(',', normalizeCsvArrayField($data['allowed_roles'] ?? 'admin,editor,viewer')),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':updated_at' => now(),
            ':id' => $id,
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Template updated.',
        ]);
    }

    if ($method === 'DELETE' && $id > 0) {
        requireAdmin();
        $stmt = db()->prepare('DELETE FROM templates WHERE id = :id');
        $stmt->execute([':id' => $id]);
        jsonResponse([
            'success' => true,
            'message' => 'Template deleted.',
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Templates route not found.',
    ], 404);
}

$parts = segments();
$root = $parts[0] ?? '';

match ($root) {
    '', 'index' => jsonResponse([
        'success' => true,
        'project' => 'DoG',
        'phase' => 'day-2',
        'routes' => [
            'auth/login',
            'auth/logout',
            'auth/me',
            'dashboard',
            'users',
            'templates',
        ],
    ]),
    'auth' => handleAuth($parts),
    'dashboard' => handleDashboard(),
    'users' => handleUsers(),
    'templates' => handleTemplates(),
    default => jsonResponse([
        'success' => false,
        'message' => 'Route not found.',
    ], 404),
};
