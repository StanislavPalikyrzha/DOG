# DoG: Document Web Generator

DoG is a web application built for the "Tehnologii Web" project. It generates and manages HTML and PDF documents from reusable templates, using manual JSON input, realistic random data, or CSV imports.

## Stack

- PHP 8 with plain server-side functions
- SQLite accessed through PDO prepared statements
- HTML, CSS and vanilla JavaScript with asynchronous `fetch()` calls
- JWT authentication with Bearer tokens
- No client-side or server-side web frameworks

## Main features

- role-based login for `admin`, `editor` and `viewer` using JWT
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
- `http://127.0.0.1:8000/docs/scholarly.html`

3. Demo accounts:

- `admin@dog.local / admin123`
- `editor@dog.local / editor123`
- `viewer@dog.local / viewer123`

## Project structure

- `index.php` - main responsive interface
- `api.php` - AJAX endpoints and download actions
- `bootstrap.php` - common setup helpers
- `init_db.php` - SQLite initialization routine
- `lib/` - database, auth, templating and PDF helper logic
- `assets/` - CSS and browser JavaScript
- `docs/` - mandatory project deliverables
- `samples/` - CSV examples for imports

## Security notes

- login uses JWT tokens sent in the `Authorization` header
- all database writes use PDO prepared statements
- generated template values are HTML-escaped on the server
- document preview normalization strips script tags before rendering
- role checks protect admin-only actions

## Open-source licensing

- project source code: MIT License, see `LICENSE`
- documentation and sample content: CC BY 4.0, see `CONTENT-LICENSE.md`
