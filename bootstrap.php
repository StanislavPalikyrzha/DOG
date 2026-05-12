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
