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
