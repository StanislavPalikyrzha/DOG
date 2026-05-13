<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function bootstrap(): void
    {
        if (!is_dir(dirname(DATABASE_FILE))) {
            mkdir(dirname(DATABASE_FILE), 0777, true);
        }

        if (!is_dir(EXPORTS_DIR)) {
            mkdir(EXPORTS_DIR, 0777, true);
        }

        if (!file_exists(DATABASE_FILE)) {
            self::initialize();
        }
    }

    public static function pdo(): PDO
    {
        self::bootstrap();

        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . DATABASE_FILE);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        if (file_exists(DATABASE_FILE)) {
            unlink(DATABASE_FILE);
        }

        foreach (glob(EXPORTS_DIR . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::initialize();
    }

    private static function initialize(): void
    {
        $pdo = new PDO('sqlite:' . DATABASE_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schema = <<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'editor', 'viewer')),
    created_at TEXT NOT NULL
);

CREATE TABLE templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL,
    description TEXT NOT NULL,
    template_html TEXT NOT NULL,
    template_css TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    template_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    source_type TEXT NOT NULL,
    data_json TEXT NOT NULL,
    html_output TEXT NOT NULL,
    pdf_path TEXT NOT NULL,
