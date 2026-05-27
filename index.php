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
