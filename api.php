<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? 'bootstrap';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = request_json();

if ($action === 'bootstrap') {
    $user = current_user();
    json_response([
        'ok' => true,
        'user' => $user ? [
            'id' => (int) $user['id'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ] : null,
        'stats' => DocumentRepository::recentStats(),
        'templates' => TemplateRepository::listAll(),
        'documents' => DocumentRepository::listAll(),
        'imports' => DocumentRepository::listImports(),
    ]);
}

if ($action === 'login' && $method === 'POST') {
    $email = trim((string) ($payload['email'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $user = Auth::attemptLogin($email, $password);
    if ($user === null) {
        json_response(['ok' => false, 'error' => 'Invalid email or password.'], 422);
    }

    json_response([
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ]);
}

if ($action === 'logout' && $method === 'POST') {
    Auth::logout(current_user());
    json_response(['ok' => true]);
}

if ($action === 'templates') {
    require_user();
    json_response(['ok' => true, 'templates' => TemplateRepository::listAll()]);
}

if ($action === 'template_create' && $method === 'POST') {
    $user = require_admin();
    $templateId = TemplateRepository::create([
        'name' => trim((string) ($payload['name'] ?? 'Untitled template')),
        'slug' => trim((string) ($payload['slug'] ?? ('template-' . random_int(100, 999)))),
        'category' => trim((string) ($payload['category'] ?? 'Custom')),
        'description' => trim((string) ($payload['description'] ?? 'Created from admin panel.')),
        'template_html' => (string) ($payload['template_html'] ?? '<p>{{title}}</p>'),
        'template_css' => (string) ($payload['template_css'] ?? ''),
    ]);
    AuditRepository::log($user['email'], 'template.create', 'Created template #' . $templateId);
    json_response(['ok' => true, 'template_id' => $templateId]);
}

