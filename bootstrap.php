<?php

date_default_timezone_set('Europe/Bucharest');

define('APP_ROOT', __DIR__);
define('DATABASE_FILE', APP_ROOT . '/database/dog.sqlite');
define('EXPORTS_DIR', APP_ROOT . '/exports');
define('JWT_SECRET', 'dog-tw-jwt-secret-2026');
define('JWT_TTL_SECONDS', 60 * 60 * 12);

require_once APP_ROOT . '/lib/Database.php';
require_once APP_ROOT . '/lib/Repositories.php';
require_once APP_ROOT . '/lib/Auth.php';
require_once APP_ROOT . '/lib/Jwt.php';
require_once APP_ROOT . '/lib/TemplateEngine.php';
require_once APP_ROOT . '/lib/FakeData.php';
require_once APP_ROOT . '/lib/PdfBuilder.php';

db_bootstrap();

function json_response($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json()
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function current_user()
{
    $token = request_bearer_token();
    $payload = jwt_decode_token($token);

    if (!$payload || empty($payload['sub'])) {
        return null;
    }

    return user_find_by_id((int) $payload['sub']);
}

function require_user()
{
    $user = current_user();

    if (!$user) {
        json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
    }

    return $user;
}

function require_admin()
{
    $user = require_user();

    if (($user['role'] ?? '') !== 'admin') {
        json_response(['ok' => false, 'error' => 'Admin access required.'], 403);
    }

    return $user;
}

function require_editor()
{
    $user = require_user();

    if (!in_array($user['role'] ?? '', ['admin', 'editor'], true)) {
        json_response(['ok' => false, 'error' => 'Editor access required.'], 403);
    }

    return $user;
}

function html_page($title, $body)
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>' .
        '<style>body{font-family:Georgia,serif;margin:2rem;background:#faf7f2;color:#231f1a}article{max-width:820px;margin:0 auto;background:#fff;padding:2.5rem;border:1px solid #d8d0c5}h1,h2,h3{margin-top:0}small{color:#6a6157}</style>' .
        '</head><body><article>' . $body . '</article></body></html>';
}

function document_links($document_id)
{
    return [
        'json' => 'api.php?action=download_json&id=' . $document_id,
        'html' => 'api.php?action=download_html&id=' . $document_id,
        'pdf' => 'api.php?action=download_pdf&id=' . $document_id,
    ];
}
