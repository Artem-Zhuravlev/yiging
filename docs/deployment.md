# Deployment

Yijing deploys as **static frontend assets + a PHP API + a SQLite file** — no Docker, no VM,
no Node.js runtime, no managed database service. See [SPEC-001](../specs/project-architecture/spec.md)
for the constraints this follows.

```
Browser → static Vue assets (served by the web server) → PHP API (public/index.php) → SQLite
```

## Requirements

- PHP 8.2 or newer, with extensions: `pdo_sqlite`, `mbstring`, `json`, `openssl`
- A web server: Apache (with `mod_php` or PHP-FPM) or Nginx + PHP-FPM
- Write access to `apps/api/database/` (where the SQLite file lives) and `apps/api/.env`
- Composer, to install `apps/api`'s PHP dependencies (`nikic/fast-route`,
  `symfony/http-foundation`, `yijing/core`) — run once at deploy time, not at request time
- Node.js is **only** needed to *build* the frontend (`npm run build`). It is not required on
  the production server; ship the resulting `apps/web/dist/` directory instead.

## Build

From the repo root, on any machine with Node.js installed (a CI runner, or your own laptop —
not the production server):

```bash
npm install
npm run build
```

This produces `apps/web/dist/` — a set of static files (HTML/CSS/JS) with no server-side
dependency.

On the production server:

```bash
cd apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env   # then edit DATABASE_PATH / APP_ENV as needed
php ../../scripts/migrate.php
php ../../scripts/seed.php
```

AI interpretation (SPEC-008/011) defaults to the mock provider — no key needed, safe to leave
as-is. To use real Gemini-backed interpretations, also set `AI_PROVIDER=gemini` and
`AI_API_KEY` in `.env` (see `.env.example` for details and where to get a key). The key is
backend-only — nothing in `apps/web` ever sees it, and it never appears in an API response.

## Directory layout on the server

```
/var/www/yijing/
├── web/            ← contents of apps/web/dist/   (served as static files)
└── api/             ← apps/api/ (vendor/, public/, src/, database/, .env)
```

The **document root for the frontend** is `web/` (or wherever `dist/` was copied). The
**document root for the API** is `api/public/` — never point a web server at `api/` itself,
only at `api/public/`, so `vendor/`, `src/`, `.env`, and the SQLite database stay outside the
web-servable path.

## Generic shared hosting (cPanel-style)

1. Upload `apps/web/dist/*` to your domain's public HTML directory (e.g. `public_html/`).
2. Upload `apps/api/` (after running `composer install` locally, since shared hosts often lack
   shell/Composer access) to a directory **outside** `public_html/`, e.g. `~/yijing-api/`.
3. Create a subdomain or subdirectory (e.g. `public_html/api/`) whose document root points at
   `~/yijing-api/public/`. Most cPanel hosts support this via "Subdomain" + a custom document
   root, or an `.htaccess` proxy if subdomain document roots aren't available.
4. Ensure the SQLite file's directory (`apps/api/database/`) is writable by the PHP process
   user, and is **not** inside a web-servable directory.
5. Point the frontend's API base URL (build-time env var, see `apps/web/.env` once introduced
   by a later spec) at the API subdomain/path.

## Apache

Document root for the API: `apps/api/public/`. Enable `mod_rewrite` and add
`apps/api/public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

Document root for the frontend: `apps/web/dist/`. If serving both from one Apache vhost, alias
`/api` to the API's document root, or run the API as a separate vhost/subdomain (simpler, and
what the health-check and CORS story assume for now).

## Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/yijing/api/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(env|git) {
        deny all;
    }
}

server {
    listen 80;
    server_name example.com;
    root /var/www/yijing/web;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## VPS without Docker

Same as the Nginx/Apache instructions above, run directly on the host: install PHP 8.2+ and
Composer via the distro's package manager (no containers), install Nginx or Apache, copy the
built frontend and the API's `vendor/`-installed backend into place, run the migration script,
and point the web server at the two document roots as shown above. A process manager (systemd
unit for `php-fpm`, which most distros already ship as a service) is the only "long-running
process" involved — there is no separate application server or worker process to manage.

## What NOT to do

- Don't point any web server's document root at the repo root, `apps/api/` (without `/public`),
  or anywhere containing `vendor/`, `.env`, or the SQLite database file.
- Don't run `apps/web`'s Vite dev server in production — always deploy the `dist/` build output.
- Don't install Composer dev dependencies (`phpunit`, `phpstan`, `php-cs-fixer`) in production —
  use `composer install --no-dev`.
