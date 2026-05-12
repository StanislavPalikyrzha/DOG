<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Bucharest');

define('APP_ROOT', __DIR__);
define('DATABASE_FILE', APP_ROOT . '/database/dog.sqlite');
define('EXPORTS_DIR', APP_ROOT . '/exports');

require_once APP_ROOT . '/lib/Database.php';
require_once APP_ROOT . '/lib/Auth.php';
require_once APP_ROOT . '/lib/Repositories.php';
require_once APP_ROOT . '/lib/TemplateEngine.php';
require_once APP_ROOT . '/lib/FakeData.php';
require_once APP_ROOT . '/lib/PdfBuilder.php';

Database::bootstrap();

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return UserRepository::findById((int) $_SESSION['user_id']);
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
    }

    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if (($user['role'] ?? '') !== 'admin') {
        json_response(['ok' => false, 'error' => 'Admin access required.'], 403);
    }

    return $user;
}

function require_editor(): array
{
    $user = require_user();
    if (!in_array($user['role'] ?? '', ['admin', 'editor'], true)) {
        json_response(['ok' => false, 'error' => 'Editor access required.'], 403);
    }

    return $user;
}

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
}

function html_page(string $title, string $body): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>' .
        '<style>body{font-family:Georgia,serif;margin:2rem;background:#faf7f2;color:#231f1a}article{max-width:820px;margin:0 auto;background:#fff;padding:2.5rem;border:1px solid #d8d0c5}h1,h2,h3{margin-top:0}small{color:#6a6157}</style>' .
        '</head><body><article>' . $body . '</article></body></html>';
}

function document_links(int $documentId): array
{
    return [
        'json' => 'api.php?action=download_json&id=' . $documentId,
        'html' => 'api.php?action=download_html&id=' . $documentId,
        'pdf' => 'api.php?action=download_pdf&id=' . $documentId,
    ];
}

