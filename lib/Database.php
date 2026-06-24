<?php

function db_bootstrap()
{
    if (!is_dir(dirname(DATABASE_FILE))) {
        mkdir(dirname(DATABASE_FILE), 0777, true);
    }

    if (!is_dir(EXPORTS_DIR)) {
        mkdir(EXPORTS_DIR, 0777, true);
    }

    if (!file_exists(DATABASE_FILE)) {
        db_initialize();
    }
}

function db()
{
    static $pdo = null;

    db_bootstrap();

    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DATABASE_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    return $pdo;
}

function db_reset()
{
    if (file_exists(DATABASE_FILE)) {
        unlink(DATABASE_FILE);
    }

    foreach (glob(EXPORTS_DIR . '/*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    db_initialize();
}

function db_initialize()
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

    $insertUser = $pdo->prepare(
        'INSERT INTO users (display_name, email, password_hash, role, created_at) VALUES (:display_name, :email, :password_hash, :role, :created_at)'
    );
    $now = date('c');

    $users = [
        ['Admin User', 'admin@dog.local', 'admin123', 'admin'],
        ['Editor User', 'editor@dog.local', 'editor123', 'editor'],
        ['Viewer User', 'viewer@dog.local', 'viewer123', 'viewer'],
    ];

    foreach ($users as $user) {
        $insertUser->execute([
            ':display_name' => $user[0],
            ':email' => $user[1],
            ':password_hash' => password_hash($user[2], PASSWORD_DEFAULT),
            ':role' => $user[3],
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
            '.doc-header{border-bottom:2px solid #c4b79f;padding-bottom:1rem;margin-bottom:1rem}.lead{font-weight:700;color:#7a5940}h2{margin-top:1.4rem}',
        ],
        [
            'Invoice simple',
            'invoice-simple',
            'Accounting',
            'Simple invoice-like document for service quotes.',
            <<<'HTML'
<section class="doc-header">
  <h1>Invoice {{uppercase:number}}</h1>
  <p>Client: {{client}}</p>
  <p>Supplier: {{supplier}}</p>
</section>
<section>
  <h2>Summary</h2>
  <p>Service: {{service}}</p>
  <p>Amount: {{amount}}</p>
  <p>Issued at: {{today}}</p>
</section>
<section>
  {{if:notes}}<p>Notes: {{notes}}</p>{{/if:notes}}
</section>
HTML,
            '.doc-header{display:flex;flex-direction:column;gap:.4rem;padding-bottom:1rem;border-bottom:2px solid #d6cdbf}h2{margin-top:1.4rem}',
        ],
        [
            'Catalog card',
            'catalog-card',
            'Products',
            'Product card template for catalogs or mini listings.',
            <<<'HTML'
<section class="doc-header">
  <h1>{{title}}</h1>
  <p class="lead">SKU {{sku}}</p>
</section>
<section>
  <p>{{summary}}</p>
  <p>Price: {{price}}</p>
  <p>Supplier: {{supplier}}</p>
  <p>Updated: {{today}}</p>
</section>
HTML,
            '.lead{font-weight:700;letter-spacing:.08em;color:#8d6b4c}.doc-header{border-bottom:1px dashed #c7b8a1;padding-bottom:.8rem;margin-bottom:1rem}',
        ],
    ];

    foreach ($templates as $template) {
        $insertTemplate->execute([
            ':name' => $template[0],
            ':slug' => $template[1],
            ':category' => $template[2],
            ':description' => $template[3],
            ':template_html' => $template[4],
            ':template_css' => $template[5],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}
