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
