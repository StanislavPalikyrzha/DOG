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
