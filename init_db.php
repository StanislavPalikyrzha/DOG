<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

ensureStorage();

$pdo = db();

$pdo->exec(
    <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'viewer',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL,
        description TEXT DEFAULT '',
        html_content TEXT NOT NULL,
        placeholders_json TEXT NOT NULL DEFAULT '[]',
        allowed_roles TEXT NOT NULL DEFAULT 'admin,editor,viewer',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_by INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_id INTEGER,
        created_by INTEGER,
        source_type TEXT NOT NULL,
        source_name TEXT NOT NULL,
        input_payload TEXT NOT NULL,
        output_html TEXT NOT NULL,
        html_path TEXT DEFAULT '',
        pdf_path TEXT DEFAULT '',
        metadata_json TEXT NOT NULL DEFAULT '{}',
        created_at TEXT NOT NULL,
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    );
    SQL
);

$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$editorPassword = password_hash('editor123', PASSWORD_DEFAULT);
$viewerPassword = password_hash('viewer123', PASSWORD_DEFAULT);
$timestamp = now();

$seedUsers = [
    [
        'full_name' => 'Administrator DoG',
        'username' => 'admin',
        'password_hash' => $adminPassword,
        'role' => 'admin',
    ],
    [
        'full_name' => 'Elena Editor',
        'username' => 'editor',
        'password_hash' => $editorPassword,
        'role' => 'editor',
    ],
    [
        'full_name' => 'Victor Viewer',
        'username' => 'viewer',
        'password_hash' => $viewerPassword,
        'role' => 'viewer',
    ],
];

$userInsert = $pdo->prepare(
    'INSERT INTO users (full_name, username, password_hash, role, is_active, created_at, updated_at)
     VALUES (:full_name, :username, :password_hash, :role, 1, :created_at, :updated_at)'
);

foreach ($seedUsers as $seedUser) {
    $check = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $check->execute([':username' => $seedUser['username']]);
    if (!$check->fetch()) {
        $userInsert->execute([
            ':full_name' => $seedUser['full_name'],
            ':username' => $seedUser['username'],
            ':password_hash' => $seedUser['password_hash'],
            ':role' => $seedUser['role'],
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}

$adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetchColumn();

$seedTemplates = [
    [
        'title' => 'CV / Resume Builder',
        'slug' => 'cv-resume-builder',
        'category' => 'cv',
        'description' => 'Resume template with conditional portfolio link rendering.',
        'html_content' => <<<'HTML'
<article class="doc-card">
    <header class="doc-head">
        <div>
            <div class="doc-kicker">Curriculum Vitae</div>
            <h1>{{full_name}}</h1>
            <p class="doc-subtitle">{{job_title}}</p>
        </div>
        <div class="doc-side">
            <p><strong>Email:</strong> {{email}}</p>
            <p><strong>Phone:</strong> {{phone}}</p>
            <p><strong>City:</strong> {{city}}</p>
        </div>
    </header>

    <section>
        <h2>Profile</h2>
        <p>{{summary}}</p>
    </section>

    <section>
        <h2>Key Skills</h2>
        <p>{{skills}}</p>
    </section>

    <section>
        <h2>Experience</h2>
        <p>{{experience_years}} years of experience. Most recent employer: {{company_name}}.</p>
    </section>

    {{if:portfolio_url}}
    <section>
        <h2>Portfolio</h2>
        <p>{{portfolio_url}}</p>
    </section>
    {{endif}}

    <footer class="doc-footer">Generated on {{date:d.m.Y}} at {{time:H:i}}</footer>
</article>
HTML,
        'placeholders_json' => json_encode([
            'full_name', 'job_title', 'email', 'phone', 'city', 'summary',
            'skills', 'experience_years', 'company_name', 'portfolio_url',
        ], JSON_UNESCAPED_UNICODE),
        'allowed_roles' => 'admin,editor,viewer',
    ],
    [
        'title' => 'Invoice / Billing Template',
        'slug' => 'invoice-billing-template',
        'category' => 'invoice',
        'description' => 'Invoice template with conditional discount notes and dynamic dates.',
        'html_content' => <<<'HTML'
<article class="doc-card">
    <header class="doc-head">
        <div>
            <div class="doc-kicker">Invoice</div>
            <h1>Invoice {{invoice_number}}</h1>
            <p class="doc-subtitle">{{company_name}}</p>
        </div>
        <div class="doc-side">
            <p><strong>Date:</strong> {{date:d.m.Y}}</p>
            <p><strong>Client:</strong> {{client_name}}</p>
            <p><strong>Email:</strong> {{client_email}}</p>
        </div>
    </header>

    <section>
        <h2>Service Description</h2>
        <p>{{service_name}}</p>
    </section>

    <section class="invoice-grid">
        <p><strong>Quantity:</strong> {{quantity}}</p>
        <p><strong>Unit Price:</strong> {{currency:unit_price}}</p>
        <p><strong>VAT:</strong> {{vat_percent}}%</p>
        <p><strong>Total:</strong> {{currency:total_amount}}</p>
    </section>

    {{if:discount_note}}
    <section>
        <h2>Discount</h2>
        <p>{{discount_note}}</p>
    </section>
    {{endif}}

    <footer class="doc-footer">Please pay by {{due_date}}</footer>
</article>
HTML,
        'placeholders_json' => json_encode([
            'invoice_number', 'company_name', 'client_name', 'client_email',
            'service_name', 'quantity', 'unit_price', 'vat_percent',
            'total_amount', 'discount_note', 'due_date',
        ], JSON_UNESCAPED_UNICODE),
        'allowed_roles' => 'admin,editor',
    ],
    [
        'title' => 'Product Catalog Card',
        'slug' => 'product-catalog-card',
        'category' => 'catalog',
        'description' => 'Product catalog card template.',
        'html_content' => <<<'HTML'
<article class="doc-card product-card">
    <header class="doc-head">
        <div>
            <div class="doc-kicker">Product Sheet</div>
            <h1>{{product_name}}</h1>
            <p class="doc-subtitle">{{category_name}}</p>
        </div>
        <div class="doc-side">
            <p><strong>SKU:</strong> {{sku}}</p>
            <p><strong>Price:</strong> {{currency:price}}</p>
            <p><strong>Updated:</strong> {{date:d.m.Y}}</p>
        </div>
    </header>

    <section>
        <h2>Description</h2>
        <p>{{product_description}}</p>
    </section>

    <section>
        <h2>Vendor</h2>
        <p>{{vendor_name}}</p>
    </section>

    {{if:stock_status}}
    <section>
        <h2>Availability</h2>
        <p>{{stock_status}}</p>
    </section>
    {{endif}}

    <footer class="doc-footer">Catalog entry {{upper:category_name}}</footer>
</article>
HTML,
        'placeholders_json' => json_encode([
            'product_name', 'category_name', 'sku', 'price',
            'product_description', 'vendor_name', 'stock_status',
        ], JSON_UNESCAPED_UNICODE),
        'allowed_roles' => 'admin,editor,viewer',
    ],
];

$templateInsert = $pdo->prepare(
    'INSERT INTO templates (title, slug, category, description, html_content, placeholders_json, allowed_roles, is_active, created_by, created_at, updated_at)
     VALUES (:title, :slug, :category, :description, :html_content, :placeholders_json, :allowed_roles, 1, :created_by, :created_at, :updated_at)'
);

foreach ($seedTemplates as $seedTemplate) {
    $check = $pdo->prepare('SELECT id FROM templates WHERE slug = :slug LIMIT 1');
    $check->execute([':slug' => $seedTemplate['slug']]);
    if (!$check->fetch()) {
        $templateInsert->execute([
            ':title' => $seedTemplate['title'],
            ':slug' => $seedTemplate['slug'],
            ':category' => $seedTemplate['category'],
            ':description' => $seedTemplate['description'],
            ':html_content' => $seedTemplate['html_content'],
            ':placeholders_json' => $seedTemplate['placeholders_json'],
            ':allowed_roles' => $seedTemplate['allowed_roles'],
            ':created_by' => $adminId > 0 ? $adminId : null,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "DoG database initialized successfully.\n";
echo "SQLite: " . DOG_DB_PATH . "\n";
echo "Admin username: admin\n";
echo "Admin password: admin123\n";
