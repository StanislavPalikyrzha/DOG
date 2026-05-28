<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DoG: Document Web Generator</title>
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
  <div class="page-shell">
    <aside class="sidebar">
      <h1>DoG</h1>
      <p>Document generator and manager built with plain Web technologies.</p>
      <nav class="nav-list">
        <a href="#dashboard">Dashboard</a>
        <a href="#generator">Generator</a>
        <a href="#documents">Documents</a>
        <a href="#admin">Admin</a>
        <a href="#resources">Resources</a>
      </nav>
      <section class="tip-box">
        <h2>Demo accounts</h2>
        <p><code>admin@dog.local / admin123</code></p>
        <p><code>editor@dog.local / editor123</code></p>
        <p><code>viewer@dog.local / viewer123</code></p>
      </section>
    </aside>

    <main class="main-content">
      <header class="hero">
        <div>
          <p class="eyebrow">Tehnologii Web project</p>
          <h2>Generate HTML and PDF documents from reusable templates</h2>
          <p class="muted">The application uses a PHP backend, SQLite persistence, asynchronous fetch requests and open formats such as CSV and JSON.</p>
        </div>
        <div class="hero-actions">
          <button type="button" class="button secondary" id="logout-button" hidden>Logout</button>
          <a class="button secondary" href="docs/scholarly.html">Scholarly HTML</a>
          <a class="button secondary" href="docs/architecture.html">Architecture</a>
        </div>
      </header>

      <section class="panel" id="login-panel">
        <div class="panel-header">
          <div>
            <p class="panel-kicker">Authentication</p>
            <h3>Login</h3>
          </div>
        </div>
        <form id="login-form" class="form-grid">
          <label>
            <span>Email</span>
            <input type="email" name="email" value="editor@dog.local" required>
          </label>
          <label>
            <span>Password</span>
            <input type="password" name="password" value="editor123" required>
          </label>
          <button type="submit" class="button primary">Sign in</button>
        </form>
        <p class="feedback" id="login-feedback"></p>
      </section>

      <section class="panel hidden" id="dashboard">
        <div class="panel-header">
          <div>
            <p class="panel-kicker">Overview</p>
            <h3>Dashboard</h3>
          </div>
          <div class="session-chip" id="session-chip"></div>
        </div>
        <div class="stats-grid" id="stats-grid"></div>
      </section>

      <section class="two-column hidden">
        <section class="panel" id="generator">
          <div class="panel-header">
            <div>
              <p class="panel-kicker">Core feature</p>
              <h3>Generate a document</h3>
            </div>
            <div class="tag-row">
              <span class="tag">HTML</span>
              <span class="tag">PDF</span>
              <span class="tag">CSV / JSON</span>
            </div>
          </div>
          <form id="generate-form" class="form-grid">
            <label>
              <span>Title</span>
              <input type="text" name="title" value="Demo document" required>
            </label>
            <label>
              <span>Template</span>
              <select name="template_id" id="template-select"></select>
            </label>
            <label>
              <span>Mode</span>
              <select name="mode" id="mode-select">
                <option value="manual">Manual JSON</option>
                <option value="random">Random realistic data</option>
                <option value="csv">CSV import</option>
              </select>
            </label>
            <label class="full-width">
              <span>JSON payload</span>
              <textarea name="json_payload" id="json-payload">{
  "name": "Maria Ionescu",
  "role": "Frontend intern",
  "summary": "Builds accessible interfaces and documents integration flows.",
  "email": "maria.ionescu@example.com",
  "phone": "+40 745 101 101",
  "city": "Iasi",
  "skills": "HTML, CSS, Fetch API, PHP basics",
  "portfolio": "https://example.test/maria"
}</textarea>
            </label>
            <label class="full-width hidden" id="csv-field">
              <span>CSV source</span>
              <textarea name="csv_payload" id="csv-payload" placeholder="name,role,email"></textarea>
            </label>
            <label class="full-width hidden" id="csv-upload-field">
              <span>CSV file upload</span>
              <input type="file" id="csv-file" accept=".csv,text/csv">
            </label>
            <button type="submit" class="button primary">Generate</button>
          </form>
          <p class="feedback" id="generate-feedback"></p>
        </section>

        <section class="panel" id="preview">
          <div class="panel-header">
            <div>
              <p class="panel-kicker">Result</p>
              <h3>Latest preview</h3>
            </div>
            <div class="link-row">
              <a class="button secondary disabled" id="open-json-link" href="#">Open JSON</a>
              <a class="button secondary disabled" id="open-html-link" href="#">Open HTML</a>
              <a class="button secondary disabled" id="open-pdf-link" href="#">Open PDF</a>
            </div>
          </div>
          <div class="preview-frame" id="preview-frame">
            <p class="placeholder">Generate a document to see the server-rendered preview.</p>
          </div>
        </section>
      </section>

      <section class="panel hidden" id="documents">
        <div class="panel-header">
          <div>
            <p class="panel-kicker">Storage</p>
            <h3>Generated documents</h3>
          </div>
        </div>
        <div id="documents-list" class="card-list"></div>
      </section>

      <section class="panel hidden" id="admin">
        <div class="panel-header">
          <div>
            <p class="panel-kicker">Administration</p>
            <h3>Users, templates and audit log</h3>
          </div>
          <span class="tag">Admin only</span>
        </div>

        <div class="admin-grid">
          <div>
            <h4>Users</h4>
            <div id="users-table"></div>
          </div>
          <div>
            <h4>Create template</h4>
            <form id="template-form" class="form-grid compact">
              <label><span>Name</span><input type="text" name="name" value="Memo note"></label>
              <label><span>Slug</span><input type="text" name="slug" value="memo-note"></label>
              <label><span>Category</span><input type="text" name="category" value="Office"></label>
              <label class="full-width"><span>Description</span><input type="text" name="description" value="Short internal memo template"></label>
              <label class="full-width"><span>Template HTML</span><textarea name="template_html"><h2>{{title}}</h2><p>{{message}}</p><small>{{today}}</small></textarea></label>
              <label class="full-width"><span>Template CSS</span><textarea name="template_css">h2{color:#6e4f32}</textarea></label>
              <button type="submit" class="button primary">Save template</button>
            </form>
            <p class="feedback" id="template-feedback"></p>
          </div>
        </div>

        <div class="admin-grid">
          <div>
            <h4>Recent imports</h4>
            <div id="imports-list" class="simple-list"></div>
          </div>
          <div>
            <h4>Audit log</h4>
            <div id="audit-list" class="simple-list"></div>
          </div>
        </div>
      </section>

      <section class="panel" id="resources">
        <div class="panel-header">
          <div>
            <p class="panel-kicker">Deliverables</p>
            <h3>Project resources</h3>
          </div>
        </div>
        <div class="resource-grid">
          <a href="docs/scholarly.html">Scholarly HTML report</a>
          <a href="docs/architecture.html">Architecture and C4 view</a>
          <a href="docs/design-notes.html">Design notes and wireframe</a>
          <a href="docs/data-provenance.html">Data model and provenance</a>
          <a href="docs/progress.html">Development progress</a>
          <a href="docs/development-log.html">Development log</a>
          <a href="docs/dog-demo-video.webm">Demo video (UHD walkthrough)</a>
          <a href="samples/cv_data.csv">CSV sample: CV</a>
          <a href="samples/invoice_data.csv">CSV sample: invoice</a>
          <a href="LICENSE">License</a>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/app.js"></script>
</body>
</html>

