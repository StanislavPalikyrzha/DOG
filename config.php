<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Bucharest');

const DOG_DB_PATH = __DIR__ . '/database/dog.sqlite';
const DOG_EXPORTS_DIR = __DIR__ . '/exports';
const DOG_DATA_DIR = __DIR__ . '/data';

function ensureStorage(): void
{
    foreach ([dirname(DOG_DB_PATH), DOG_EXPORTS_DIR, DOG_DATA_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ensureStorage();

    $pdo = new PDO('sqlite:' . DOG_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function safeJsonDecode(?string $json, array $fallback = []): array
{
    if ($json === null || trim($json) === '') {
        return $fallback;
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : $fallback;
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['PUT', 'PATCH', 'DELETE'], true)) {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            parse_str($raw, $parsed);
            if (is_array($parsed) && $parsed !== []) {
                return $parsed;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
    }

    return $_POST;
}

function currentUser(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, username, role, is_active, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user || (int) $user['is_active'] !== 1) {
        jsonResponse([
            'success' => false,
            'message' => 'Authentication is required.',
        ], 401);
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireAuth();
    if (($user['role'] ?? '') !== 'admin') {
        jsonResponse([
            'success' => false,
            'message' => 'Administrator access only.',
        ], 403);
    }

    return $user;
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $translit = $translit ?: $value;
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $translit);
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? $slug : 'template-' . time();
}

function escapeValue(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalizeCsvArrayField(string|array|null $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), static fn ($item) => $item !== ''));
    }

    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($item) => $item !== ''));
}

function baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api.php';
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $scheme . '://' . $host . ($directory === '' ? '' : $directory);
}
