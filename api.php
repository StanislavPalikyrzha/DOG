<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

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

function userIsAdmin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
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

$parts = segments();
$root = $parts[0] ?? '';

match ($root) {
    '', 'index' => jsonResponse([
        'success' => true,
        'project' => 'DoG',
        'phase' => 'day-1',
        'routes' => [
            'auth/login',
            'auth/logout',
            'auth/me',
            'dashboard',
        ],
    ]),
    'auth' => handleAuth($parts),
    'dashboard' => handleDashboard(),
    default => jsonResponse([
        'success' => false,
        'message' => 'Route not found.',
    ], 404),
};
