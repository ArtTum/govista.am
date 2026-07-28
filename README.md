# GoVista

GoVista is a multilingual Armenia travel platform with:

- Laravel 13 API and authentication
- Vue 3 administration studio
- Nuxt 4.5 public frontend
- Armenian, Russian and English content
- Tours, destinations, services, blog, pages, FAQs, reviews, bookings and contact leads
- Seeded demo content and media upload support

Production server configuration, secrets, services, TLS and the go-live
checklist are documented in [`PRODUCTION.md`](./PRODUCTION.md).

## Local development

Requirements: PHP 8.3+, Composer, Node.js 22+ and npm.

### Backend and admin

```bash
cd backend
composer install
php artisan migrate:fresh --seed
php artisan storage:link
npm install
npm run dev
php artisan serve
```

Admin: `http://127.0.0.1:8000/admin`

In this workspace the verified preview uses port `8010` because `8000` is already
occupied:

```powershell
New-Item -ItemType Directory -Force storage\app\tmp-upload
Set-Location public
D:\projects\php83\php.exe -d upload_tmp_dir=D:\projects\htdocs\domains\govista.am\backend\storage\app\tmp-upload -S 127.0.0.1:8010 D:\projects\htdocs\domains\govista.am\backend\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Preview admin: `http://127.0.0.1:8010/admin`

Seeded login:

- Email: `admin@govista.am`
- Password: `GoVista2026!`

Change this password immediately in a production environment.

### Nuxt frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Frontend: `http://localhost:3000/hy`

For MySQL, copy `backend/.env.example` to `backend/.env`, keep `DB_DATABASE=govista`,
set your database credentials, and run migrations with seed data.

## Verification

Run the automated Laravel regression suite:

```powershell
cd backend
D:\projects\php83\php.exe artisan test
```

Run every live public/admin API request, including CRUD, validation, upload,
booking, contact and cleanup:

```powershell
cd D:\projects\htdocs\domains\govista.am
.\scripts\runtime-smoke.ps1
```

The runtime smoke test deletes every QA record and uploaded QA image it creates.

Validate the compiled Nuxt server, all 63 localized public URLs, SEO metadata,
sitemap, robots, assets and error pages:

```powershell
node scripts\production-smoke.mjs
```

Validate the compiled site at an exact 390×844 mobile viewport with local
Chrome and create `.runtime/mobile-home-exact.png` for visual review:

```powershell
node scripts\mobile-visual-smoke.mjs
```
