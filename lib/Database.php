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
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY(template_id) REFERENCES templates(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE import_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    row_count INTEGER NOT NULL,
    status TEXT NOT NULL,
    notes TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_email TEXT NOT NULL,
    action TEXT NOT NULL,
    details TEXT NOT NULL,
    created_at TEXT NOT NULL
);
SQL;

        $pdo->exec($schema);

        $now = date('c');
        $insertUser = $pdo->prepare(
            'INSERT INTO users (display_name, email, password_hash, role, created_at) VALUES (:display_name, :email, :password_hash, :role, :created_at)'
        );

        foreach ([
            ['Admin User', 'admin@dog.local', 'admin123', 'admin'],
            ['Editor User', 'editor@dog.local', 'editor123', 'editor'],
            ['Viewer User', 'viewer@dog.local', 'viewer123', 'viewer'],
        ] as [$name, $email, $password, $role]) {
            $insertUser->execute([
                ':display_name' => $name,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':created_at' => $now,
            ]);
        }

        $insertTemplate = $pdo->prepare(
            'INSERT INTO templates (name, slug, category, description, template_html, template_css, created_at, updated_at)
             VALUES (:name, :slug, :category, :description, :template_html, :template_css, :created_at, :updated_at)'
        );

        $templates = [
            [
                'CV academic',
                'cv-academic',
                'CV',
                'Template for student CVs generated from JSON or CSV data.',
                <<<'HTML'
<section class="doc-header">
  <h1>{{name}}</h1>
  <p class="lead">{{role}}</p>
  <p>{{summary}}</p>
</section>
<section>
  <h2>Contact</h2>
  <ul>
    <li>Email: {{email}}</li>
    <li>Phone: {{phone}}</li>
    <li>City: {{city}}</li>
  </ul>
</section>
<section>
  <h2>Skills</h2>
  <p>{{skills}}</p>
</section>
<section>
  <h2>Availability</h2>
  {{if:portfolio}}<p>Portfolio: {{portfolio}}</p>{{/if:portfolio}}
  <small>Generated on {{today}}</small>
</section>
HTML,
                '.doc-header{border-bottom:2px solid #c4b79f;padding-bottom:1rem;margin-bottom:1rem}.lead{font-weight:700;color:#7a5940}h2{margin-top:1.4rem}'
            ],
            [
                'Invoice simple',
