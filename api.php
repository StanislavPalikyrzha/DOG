<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? 'bootstrap';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = request_json();

if ($action === 'bootstrap') {
    $user = current_user();
    json_response([
        'ok' => true,
        'user' => $user ? [
            'id' => (int) $user['id'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ] : null,
        'stats' => DocumentRepository::recentStats(),
        'templates' => TemplateRepository::listAll(),
        'documents' => DocumentRepository::listAll(),
        'imports' => DocumentRepository::listImports(),
    ]);
}

if ($action === 'login' && $method === 'POST') {
    $email = trim((string) ($payload['email'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $user = Auth::attemptLogin($email, $password);
    if ($user === null) {
        json_response(['ok' => false, 'error' => 'Invalid email or password.'], 422);
    }

    json_response([
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ]);
}

if ($action === 'logout' && $method === 'POST') {
    Auth::logout(current_user());
    json_response(['ok' => true]);
}

if ($action === 'templates') {
    require_user();
    json_response(['ok' => true, 'templates' => TemplateRepository::listAll()]);
}

if ($action === 'template_create' && $method === 'POST') {
    $user = require_admin();
    $templateId = TemplateRepository::create([
        'name' => trim((string) ($payload['name'] ?? 'Untitled template')),
        'slug' => trim((string) ($payload['slug'] ?? ('template-' . random_int(100, 999)))),
        'category' => trim((string) ($payload['category'] ?? 'Custom')),
        'description' => trim((string) ($payload['description'] ?? 'Created from admin panel.')),
        'template_html' => (string) ($payload['template_html'] ?? '<p>{{title}}</p>'),
        'template_css' => (string) ($payload['template_css'] ?? ''),
    ]);
    AuditRepository::log($user['email'], 'template.create', 'Created template #' . $templateId);
    json_response(['ok' => true, 'template_id' => $templateId]);
}

if ($action === 'generate' && $method === 'POST') {
    $user = require_editor();
    $template = TemplateRepository::findById((int) ($payload['template_id'] ?? 0));
    if ($template === null) {
        json_response(['ok' => false, 'error' => 'Template not found.'], 404);
    }

    $mode = (string) ($payload['mode'] ?? 'manual');
    $created = [];

    $buildDocument = static function (array $data, string $title, string $sourceType) use ($template, $user): array {
        $body = TemplateEngine::render($template['template_html'], $data);
        $html = TemplateEngine::wrapDocument($title, $template['template_css'], $body);

        $documentId = DocumentRepository::create([
            'title' => $title,
            'template_id' => (int) $template['id'],
            'status' => 'generated',
            'source_type' => $sourceType,
            'data' => $data,
            'html_output' => preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html,
            'pdf_path' => '',
            'created_by' => (int) $user['id'],
        ]);

        $pdfPath = EXPORTS_DIR . '/document-' . $documentId . '.pdf';
        PdfBuilder::build($title, $html, $pdfPath);
        DocumentRepository::updatePdfPath($documentId, basename($pdfPath));

        $document = DocumentRepository::findById($documentId);
        AuditRepository::log($user['email'], 'document.generate', 'Generated document #' . $documentId . ' from template ' . $template['slug']);
        return $document ?? [];
    };

    if ($mode === 'csv') {
        $csv = trim((string) ($payload['csv'] ?? ''));
        $rows = array_values(array_filter(preg_split('/\R/', $csv) ?: [], static fn(string $line): bool => trim($line) !== ''));
        if (count($rows) < 2) {
            json_response(['ok' => false, 'error' => 'CSV must contain a header and at least one row.'], 422);
        }

        $headers = str_getcsv((string) array_shift($rows), ',', '"', '\\');
        foreach ($rows as $index => $row) {
            $values = str_getcsv($row, ',', '"', '\\');
            $data = [];
            foreach ($headers as $offset => $header) {
                $data[trim((string) $header)] = trim((string) ($values[$offset] ?? ''));
            }
            $title = trim((string) ($data['title'] ?? $data['name'] ?? ($template['name'] . ' row ' . ($index + 1))));
            $created[] = $buildDocument($data, $title, 'csv');
        }
        DocumentRepository::logImport((string) ($payload['file_name'] ?? 'inline.csv'), count($created), 'done', 'CSV import generated documents.', (int) $user['id']);
    } elseif ($mode === 'random') {
        $data = FakeData::forTemplate($template['slug']);
        $created[] = $buildDocument($data, trim((string) ($payload['title'] ?? ($template['name'] . ' demo'))), 'random');
    } else {
        $data = $payload['data'] ?? [];
        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'Manual payload must be a JSON object.'], 422);
        }
        $created[] = $buildDocument($data, trim((string) ($payload['title'] ?? 'Manual document')), 'json');
    }

    $primary = $created[0] ?? null;
    json_response([
        'ok' => true,
        'created_count' => count($created),
        'documents' => $created,
        'preview_html' => $primary['html_output'] ?? '',
        'links' => $primary ? document_links((int) $primary['id']) : null,
    ]);
}

if ($action === 'documents') {
    require_user();
    json_response(['ok' => true, 'documents' => DocumentRepository::listAll()]);
}

if ($action === 'document') {
    require_user();
    $document = DocumentRepository::findById((int) ($_GET['id'] ?? 0));
    if ($document === null) {
        json_response(['ok' => false, 'error' => 'Document not found.'], 404);
    }
    json_response(['ok' => true, 'document' => $document, 'links' => document_links((int) $document['id'])]);
}

if ($action === 'download_html') {
    require_user();
    $document = DocumentRepository::findById((int) ($_GET['id'] ?? 0));
    if ($document === null) {
