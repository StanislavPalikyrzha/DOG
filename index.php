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
