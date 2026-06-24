<?php

require_once __DIR__ . '/bootstrap.php';

function api_user_data($user)
{
    if (!$user) {
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'display_name' => $user['display_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function load_document_or_404($id)
{
    $document = document_find_by_id((int) $id);

    if (!$document) {
        http_response_code(404);
        exit('Document not found.');
    }

    return $document;
}

function build_generated_document($template, $user, $data, $title, $source_type)
{
    $body = template_render($template['template_html'], $data);
    $html = wrap_document($title, $template['template_css'], $body);
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

    $document_id = document_create([
        'title' => $title,
        'template_id' => (int) $template['id'],
        'status' => 'generated',
        'source_type' => $source_type,
        'data' => $data,
        'html_output' => $html,
        'pdf_path' => '',
        'created_by' => (int) $user['id'],
    ]);

    $pdf_path = EXPORTS_DIR . '/document-' . $document_id . '.pdf';
    pdf_build($title, $html, $pdf_path);
    document_update_pdf_path($document_id, basename($pdf_path));
    audit_log_add($user['email'], 'document.generate', 'Generated document #' . $document_id . ' from template ' . $template['slug']);

    return document_find_by_id($document_id);
}

$action = $_GET['action'] ?? 'bootstrap';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$payload = request_json();

if ($action === 'bootstrap') {
    $user = current_user();

    json_response([
        'ok' => true,
        'user' => api_user_data($user),
        'stats' => document_recent_stats(),
        'templates' => template_list_all(),
        'documents' => document_list_all(),
        'imports' => import_list_all(),
    ]);
}

if ($action === 'login' && $method === 'POST') {
    $user = auth_attempt_login(trim((string) ($payload['email'] ?? '')), (string) ($payload['password'] ?? ''));

    if (!$user) {
        json_response(['ok' => false, 'error' => 'Invalid email or password.'], 422);
    }

    json_response([
        'ok' => true,
        'user' => api_user_data($user),
        'token' => jwt_create_token($user),
    ]);
}

if ($action === 'logout' && $method === 'POST') {
    auth_logout(current_user());
    json_response(['ok' => true]);
}

if ($action === 'templates') {
    require_user();
    json_response(['ok' => true, 'templates' => template_list_all()]);
}

if ($action === 'template_create' && $method === 'POST') {
    $user = require_admin();
    $template_id = template_create([
        'name' => trim((string) ($payload['name'] ?? 'Untitled template')),
        'slug' => trim((string) ($payload['slug'] ?? ('template-' . random_int(100, 999)))),
        'category' => trim((string) ($payload['category'] ?? 'Custom')),
        'description' => trim((string) ($payload['description'] ?? 'Created from admin panel.')),
        'template_html' => (string) ($payload['template_html'] ?? '<p>{{title}}</p>'),
        'template_css' => (string) ($payload['template_css'] ?? ''),
    ]);

    audit_log_add($user['email'], 'template.create', 'Created template #' . $template_id);
    json_response(['ok' => true, 'template_id' => $template_id]);
}

if ($action === 'generate' && $method === 'POST') {
    $user = require_editor();
    $template = template_find_by_id((int) ($payload['template_id'] ?? 0));

    if (!$template) {
        json_response(['ok' => false, 'error' => 'Template not found.'], 404);
    }

    $mode = (string) ($payload['mode'] ?? 'manual');
    $created = [];

    if ($mode === 'csv') {
        $csv = trim((string) ($payload['csv'] ?? ''));
        $rows = preg_split('/\R/', $csv) ?: [];
        $rows = array_values(array_filter($rows, 'strlen'));

        if (count($rows) < 2) {
            json_response(['ok' => false, 'error' => 'CSV must contain a header and at least one row.'], 422);
        }

        $headers = str_getcsv(array_shift($rows), ',', '"', '\\');

        foreach ($rows as $index => $row) {
            $values = str_getcsv($row, ',', '"', '\\');
            $data = [];

            foreach ($headers as $offset => $header) {
                $data[trim((string) $header)] = trim((string) ($values[$offset] ?? ''));
            }

            $title = trim((string) ($data['title'] ?? $data['name'] ?? ($template['name'] . ' row ' . ($index + 1))));
            $created[] = build_generated_document($template, $user, $data, $title, 'csv');
        }

        import_log_add((string) ($payload['file_name'] ?? 'inline.csv'), count($created), 'done', 'CSV import generated documents.', (int) $user['id']);
    } elseif ($mode === 'random') {
        $data = random_data_for_template($template['slug']);
        $title = trim((string) ($payload['title'] ?? ($template['name'] . ' demo')));
        $created[] = build_generated_document($template, $user, $data, $title, 'random');
    } else {
        $data = $payload['data'] ?? [];

        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'Manual payload must be a JSON object.'], 422);
        }

        $title = trim((string) ($payload['title'] ?? 'Manual document'));
        $created[] = build_generated_document($template, $user, $data, $title, 'json');
    }

    $first = $created[0] ?? null;

    json_response([
        'ok' => true,
        'created_count' => count($created),
        'documents' => $created,
        'preview_html' => $first['html_output'] ?? '',
        'links' => $first ? document_links((int) $first['id']) : null,
    ]);
}

if ($action === 'documents') {
    require_user();
    json_response(['ok' => true, 'documents' => document_list_all()]);
}

if ($action === 'document') {
    require_user();
    $document = document_find_by_id((int) ($_GET['id'] ?? 0));

    if (!$document) {
        json_response(['ok' => false, 'error' => 'Document not found.'], 404);
    }

    json_response(['ok' => true, 'document' => $document, 'links' => document_links((int) $document['id'])]);
}

if ($action === 'download_html') {
    require_user();
    $document = load_document_or_404($_GET['id'] ?? 0);
    header('Content-Type: text/html; charset=utf-8');
    echo html_page($document['title'], $document['html_output']);
    exit;
}

if ($action === 'download_json') {
    require_user();
    $document = load_document_or_404($_GET['id'] ?? 0);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: inline; filename="document-' . $document['id'] . '.json"');
    echo json_encode([
        'id' => (int) $document['id'],
        'title' => $document['title'],
        'template' => $document['template_name'],
        'status' => $document['status'],
        'source_type' => $document['source_type'],
        'data' => $document['data'],
        'created_at' => $document['created_at'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'download_pdf') {
    require_user();
    $document = load_document_or_404($_GET['id'] ?? 0);
    $path = EXPORTS_DIR . '/' . $document['pdf_path'];

    if (!is_file($path)) {
        http_response_code(404);
        exit('PDF not found.');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

if ($action === 'users') {
    require_admin();
    json_response([
        'ok' => true,
        'users' => user_list_all(),
        'audit' => audit_recent(),
        'imports' => import_list_all(),
    ]);
}

if ($action === 'user_role' && $method === 'POST') {
    $user = require_admin();
    user_update_role((int) ($payload['id'] ?? 0), (string) ($payload['role'] ?? 'viewer'));
    audit_log_add($user['email'], 'user.role.update', 'Updated role for user #' . (int) ($payload['id'] ?? 0));
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Unknown action.'], 404);
