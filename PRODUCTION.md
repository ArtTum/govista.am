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
3. Request indexing for `/hy`, `/hy/domestic-tours` and
   `/hy/international-tours`, then repeat for the Russian and English
   landing pages.
4. Keep only one canonical host. The supplied Nginx profile redirects
   `www.govista.am` to `govista.am`.
5. Recheck sitemap fetch status after every production deployment that changes
   routes or API connectivity.

Sitemaps and indexing requests improve discovery, but search engines decide
when pages are indexed and how they rank.

### Google and Yandex verification and analytics

Manage the site's Search Console, Analytics, Webmaster and Metrica entries
with the owner's `artur999033@gmail.com` account. Check existing entries before
creating another property or tag. A configured verification token is not proof
that ownership has been verified in the provider's dashboard.

The frontend renders verification meta tags in the initial server HTML. The
Google and Yandex tokens for `https://govista.am/`, the GA4 measurement ID, and
the Metrica tag ID are included in the runtime defaults.
Declare the provider-issued values in the frontend environment file used by
the running service (the sample systemd unit uses `.env.production`):

```dotenv
NUXT_PUBLIC_GOOGLE_SITE_VERIFICATION=vrUzvsty2BfpMzPBfBU8iid3T9xAM6DTUujMJh5gbx4
NUXT_PUBLIC_YANDEX_VERIFICATION=f100d1cbaa3add23
NUXT_PUBLIC_GOOGLE_ANALYTICS_ID=G-CG9985MZ90
NUXT_PUBLIC_YANDEX_METRICA_ID=113741781
```

Use the GA4 web stream measurement ID beginning with `G-`, and the numeric
Metrica tag ID. These are public identifiers, not account passwords or API
secrets. Never put account credentials in public runtime configuration.

The GoVista.am Google Analytics account is `413072034`, its GoVista property is
`560301302`, and the GoVista website stream is `16184382955` (measurement ID
`G-CG9985MZ90`). Enhanced Measurement was confirmed off during setup.
The GoVista Yandex Metrica tag is `113741781`. Advanced Matching and automatic
goals were explicitly turned off and saved; Session Replay is not set up.

For the manual page-view integration, turn off **Enhanced Measurement** in
the GA4 web stream, including browser-history changes, form interactions and
outbound clicks. In Metrica, turn off automatic goals, advanced matching and
session replay. The integration explicitly sends sanitized public-page views:
it strips URL queries and fragments, omits account/recovery/error pages, and
does not initialize on localhost or preview origins. It does not pass booking
form values or push ecommerce events. Metrica uses its own empty
`govistaMetricaDataLayer` so it cannot fall back to Google's shared `dataLayer`.
The dashboard's separate automatic ecommerce collection was not confirmed off;
provider libraries may send additional automatic or service events. The trackers
stay off when their IDs are blank.

The current server checkout reported by the owner is `/var/domains/govista.am`.
After the code has been pushed and pulled into that checkout, build the frontend
and restart the service that runs it:

```bash
cd /var/domains/govista.am/frontend
npm run build && sudo systemctl restart govista-nuxt
```

Confirm the Google/Yandex verification tags appear in the live page source,
then complete **Verify** in each webmaster dashboard and submit
`https://govista.am/sitemap.xml`. Confirm actual page views in GA4 Realtime and
Metrica after configuring their IDs. A successful build alone does not prove
that a counter is collecting visits or that webmaster ownership is verified.

Run the focused local tracking checks with `node scripts/site-analytics-test.mjs`.

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

The 2026-09-09 audit updated Nuxt to 4.5.2 and refreshed vulnerable
compatible npm and Composer dependencies. Both npm projects and the locked
production Composer dependencies reported zero known advisories after the
update. Keep using the committed lockfiles and rerun audits before release.
Do not use `npm audit fix --force` to resolve future advisories without
reviewing the resulting version changes.

Run `node scripts/audit-smoke.mjs` after the frontend build to verify complete
sitemap pagination, dynamic CMS pages, locale redirects, and correct 404/503
responses. It uses an isolated fixture API and does not modify real records.
The broader runtime smoke script modifies and cleans up QA records, so run it
separately from checks that expect exact catalog or sitemap counts.

## Travel service launch requirements

- Apply `2026_09_10_000400_add_travel_platform` with `php artisan migrate --force`; preserve the current database and `APP_KEY`.
- Set `FRONTEND_URL` to the public frontend origin for password recovery, and configure a working SMTP mailer. Log/array mailers deliberately cannot send recovery links.
- Configure suppliers through the dedicated admin panel. Validate sandbox responses and contractual permissions before enabling a live connection. `TRAVEL_DAILY_SEARCH_LIMIT` defaults to 200 public searches per provider per day, including Geoapify autocomplete traffic.
- The current product supports requests, manual proposals, partner redirects and manual confirmation records. It does not take payments, issue tickets, book suppliers automatically or automate refunds. Do not advertise those operations as available.
- Run `php artisan test`, both frontend builds, and the travel/production smoke scripts. Use a separate seeded test database for browser workflow testing. `runtime-smoke.ps1` now retains a cancelled QA request to preserve its audit trail.
- Detailed scope and remaining requirements: [TRAVEL_PLATFORM_IMPLEMENTATION.md](TRAVEL_PLATFORM_IMPLEMENTATION.md).

## Outbound packages

Apply `2026_09_10_001000_add_package_offers` with the normal non-destructive migration command. Rebuild both applications and restart Nuxt. Run `node scripts/package-smoke.mjs`. Configure approved supplier relationships before publishing their offers; all CSV rows start as drafts. Tourvisor search requires an account token, confirmed EVN coverage and dictionary mappings. ANRIVA, TEZ TOUR, Sletat and TBO remain manual/CSV workflows until their account-specific API adapters are implemented. Package payments and automatic booking are not enabled. See [PACKAGE_PLATFORM_IMPLEMENTATION.md](PACKAGE_PLATFORM_IMPLEMENTATION.md).

## Armenian supplier API profiles

Apply `2026_09_11_001000_add_provider_integration_profiles` and rebuild the admin. Keep `APP_KEY` unchanged: the new sandbox/live profiles are encrypted with it. ANRIVA, Maratuk, World Voyage and TravelOne have separate environment settings. Only TravelOne has a TourVisio implementation for admin authentication, location dictionaries and package search previews. Supplier-issued API addresses, credentials and permission confirmation are required; public search, booking and payments are not enabled by these profiles. Other supplier adapters still need their protocol documentation. PHP cURL and public HTTPS access are required; private/reserved destinations, redirects and proxy routing are blocked. Run `php artisan test` and `node scripts/package-smoke.mjs`. See [PROVIDER_API_SETUP.md](PROVIDER_API_SETUP.md).
