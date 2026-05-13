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
