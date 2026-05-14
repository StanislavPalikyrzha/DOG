# DoG (Document Web Generator)

DoG is a modular web system built for the Web Technologies project. This day 1 slice includes the foundation, database initialization, authentication, and dashboard base for the official `DOG` repository.

## Stack

- Frontend: HTML5 + CSS3 + Vanilla JavaScript
- Backend: plain PHP
- Database: SQLite via PDO

## Run

```bash
php init_db.php
php -S localhost:8000
```

Open:

- `http://localhost:8000`

## Demo users

- `admin / admin123`
- `editor / editor123`
- `viewer / viewer123`

## Day 1 scope

- project structure
- SQLite initialization and seed data
- authentication routes
- dashboard statistics endpoint
- minimal SPA shell for login and dashboard
