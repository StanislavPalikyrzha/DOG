# DoG: Document Web Generator

DoG is a modular web application built for the "Tehnologii Web" project. It generates and manages HTML and PDF documents from reusable templates, using manual JSON input, realistic random data, or CSV imports.

## Stack

- PHP 8 with plain server-side modules
- SQLite accessed through PDO prepared statements
- HTML, CSS and vanilla JavaScript with asynchronous `fetch()` calls
- No client-side or server-side web frameworks

## Main features

- role-based login for `admin`, `editor` and `viewer`
- document generation from reusable templates
- dynamic templating with placeholders, conditional blocks and date helpers
- import from CSV and manual JSON input
- export to HTML and PDF
- admin module for users, templates and audit log
- responsive interface and project documentation deliverables

## Quick start

1. Start a local PHP server from the project root:

```bash
php -S 127.0.0.1:8000
```

2. Open:

- `http://127.0.0.1:8000/`
