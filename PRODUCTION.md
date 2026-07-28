# GoVista production handoff

This deployment profile assumes Ubuntu, Nginx, PHP 8.3-FPM, MySQL 8 and
Node.js 22. Nginx serves Laravel under `/api`, `/admin`, `/build`, `/storage`
and `/up`; every other request is proxied to Nuxt on `127.0.0.1:3000`.

## 1. Required secrets and DNS

Create DNS records for `govista.am` and `www.govista.am`, then create:

- `backend/.env` from `backend/.env.production.example`
- `frontend/.env.production` from `frontend/.env.production.example`

Replace every `CHANGE_ME` value. Never commit either real environment file.
Generate the Laravel key on the server:

```bash
cd /var/www/govista/current/backend
php8.3 artisan key:generate
```

`ADMIN_PASSWORD` must be a new, unique password of at least 16 characters.
The production seeder refuses to run without it. The seeder creates the
administrator only when it does not already exist, so later deploys do not
reset the password.

## 2. Backend installation

```bash
cd /var/www/govista/current/backend
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php8.3 artisan migrate --force
php8.3 artisan storage:link
php8.3 artisan config:cache
php8.3 artisan route:cache
php8.3 artisan view:cache
```

Run this only for the first production installation:

```bash
php8.3 artisan db:seed --force
```

The launch seeder installs 24 editable tours (12 domestic and 12 outbound).
On an existing database, take a backup before deliberately running the seeder:
records with the same slug are updated, while unrelated admin-created content
is preserved.

Set permissions:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

## 3. Frontend installation

```bash
cd /var/www/govista/current/frontend
npm ci
npm run build
```

The public API URL must be `https://govista.am/api`. The server-side internal
URL can use local Nginx as `http://127.0.0.1/api`.

## 4. Services and Nginx

Start with the HTTP-only bootstrap configuration so Certbot can issue the
first certificate:

```bash
mkdir -p /var/www/letsencrypt
cp deploy/nginx.bootstrap.conf /etc/nginx/sites-available/govista
ln -s /etc/nginx/sites-available/govista /etc/nginx/sites-enabled/govista
nginx -t
systemctl reload nginx
certbot certonly --webroot -w /var/www/letsencrypt -d govista.am -d www.govista.am
```

After the certificate exists, install the final configuration and services:

```bash
cp deploy/govista-nuxt.service /etc/systemd/system/
cp deploy/govista-queue.service /etc/systemd/system/
cp deploy/govista-scheduler.service /etc/systemd/system/
cp deploy/govista-scheduler.timer /etc/systemd/system/
cp deploy/nginx.govista.conf /etc/nginx/sites-available/govista
```

Then validate and start:

```bash
systemctl daemon-reload
systemctl enable --now govista-nuxt govista-queue govista-scheduler.timer
nginx -t
systemctl reload nginx
```

## 5. Release commands

For later releases:

```bash
cd /var/www/govista/current/backend
php8.3 artisan down --retry=30
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php8.3 artisan migrate --force
php8.3 artisan optimize

cd /var/www/govista/current/frontend
npm ci
npm run build

systemctl restart govista-nuxt govista-queue
cd /var/www/govista/current/backend
php8.3 artisan up
```

Do not run `db:seed` during regular releases because seed content is demo
content and may overwrite content with matching slugs.

## 6. Go-live verification

```bash
curl -I https://govista.am/hy
curl -I https://govista.am/admin
curl -I https://govista.am/api/v1/home?locale=hy
curl -I https://govista.am/sitemap.xml
curl -I https://govista.am/robots.txt
```

Verify:

- HTTPS redirects and certificate renewal work.
- `/admin` contains `noindex` and is protected by the administrator account.
- A booking appears in the admin and can be confirmed.
- A PNG/JPEG/WebP upload returns a `/storage/...` URL.
- Armenian, Russian and English pages have canonical and alternate links.
- SMTP credentials are real and not set to the log mailer.
- MySQL backups run daily and are copied off-server.
- `storage/logs/laravel.log`, the Nuxt service and the queue service are
  monitored.

Before handoff, change any temporary administrator password and remove
`ADMIN_PASSWORD` from the live environment after the first successful seed.

## 7. Search engine launch

After DNS and HTTPS are live:

1. Add and verify `https://govista.am` in Google Search Console and Yandex
   Webmaster.
2. Submit `https://govista.am/sitemap.xml` in both tools.
3. Request indexing for `/hy`, `/hy/tours?scope=domestic` and
   `/hy/tours?scope=international`, then repeat for the Russian and English
   landing pages.
4. Keep only one canonical host. The supplied Nginx profile redirects
   `www.govista.am` to `govista.am`.
5. Recheck sitemap fetch status after every production deployment that changes
   routes or API connectivity.

Sitemaps and indexing requests improve discovery, but search engines decide
when pages are indexed and how they rank.

## 8. Pre-deployment quality gate

Run these from the project root before packaging a release:

```bash
cd backend
php8.3 artisan test
composer validate --strict
composer audit --locked --no-dev
npm ci
npm audit
npm run build

cd ../frontend
npm ci
npm outdated
npm run build

cd ..
node scripts/production-smoke.mjs
node scripts/mobile-visual-smoke.mjs
```

The smoke scripts expect a Laravel API at `127.0.0.1:8010`; override their
documented environment variables when a different QA endpoint is used.

At the time of this handoff, all direct Composer and npm dependencies are at
their latest compatible releases. npm reports the `brace-expansion` advisory
through Nuxt → Nitro → Archiver. It belongs to Nitro's archive/build toolchain
and is not referenced by the generated `.output` server. Do not run
`npm audit fix --force`: npm currently resolves that command by downgrading
Nuxt. Update Nuxt/Nitro normally when their upstream dependency moves to the
patched Archiver line.
