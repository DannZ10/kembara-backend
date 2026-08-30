# 🏕️ Kembara.id API — Outdoor Rental & Booking System (Backend)

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/DB-MySQL_8-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Sanctum](https://img.shields.io/badge/Auth-Sanctum_%2B_Google_OAuth-red?style=for-the-badge)](https://laravel.com/docs/sanctum)
[![Midtrans](https://img.shields.io/badge/Payment-Midtrans_Snap-blue?style=for-the-badge)](https://midtrans.com)
[![Swagger](https://img.shields.io/badge/Docs-OpenAPI_3.1-85EA2D?style=for-the-badge&logo=swagger&logoColor=black)](https://kembara-backend.onrender.com/docs/api)

REST API backend untuk **Kembara.id** — platform penyewaan perlengkapan outdoor & alat gunung. Dibangun dengan **Laravel 13 (PHP 8.3)**, arsitektur **Thin Controller / Fat Service**, **pessimistic stock locking**, perhitungan biaya & total **server-authoritative**, serta integrasi **Midtrans Snap** dan **Google OAuth**.

> **Bagian dari sistem full-stack yang decoupled** — dipasangkan dengan frontend Next.js ([kembara-frontend](https://github.com/DannZ10/kembara-frontend)).

---

## 🔗 Live

| Sumber | URL |
|---|---|
| REST API (Base URL) | `https://kembara-backend.onrender.com/api` |
| Dokumentasi API (Swagger / OpenAPI 3.1) | https://kembara-backend.onrender.com/docs/api |
| Aplikasi (Frontend) | https://kembara-frontend.vercel.app |

Login demo: **admin** `admin@kembara.com` / `admin123` · **customer** `customer@kembara.com` / `customer123`.

---

## 🧱 Tech Stack

| Kategori | Teknologi |
|---|---|
| Framework | Laravel 13, PHP 8.3 |
| Database | MySQL 8 (produksi: Aiven, TLS) |
| Autentikasi | Laravel **Sanctum** (Bearer token) + **Socialite** (Google OAuth) |
| Pembayaran | **midtrans/midtrans-php** (Snap) |
| Dokumentasi API | **dedoc/scramble** (auto-generate OpenAPI dari kode, UI di `/docs/api`) |
| Utilitas | spatie/laravel-sluggable (slug), predis |
| Deploy | Docker → **Render** · DB **Aiven** |

---

## 🏛️ Arsitektur & Prinsip

Setiap request mengalir melalui lapisan yang tegas:

```
routes/api.php → Middleware (auth:sanctum · is.admin · throttle)
             → Controller (tipis) → Form Request (validasi)
             → Service (logika bisnis) → Eloquent Model → MySQL
             → API JSON: { success, message, data, meta }
```

- **Thin Controller / Fat Service** — controller hanya orkestrasi; logika bisnis ada di `app/Services`.
- **Form Request selalu** — validasi tidak pernah inline (`app/Http/Requests`).
- **Envelope JSON konsisten** — error diseragamkan di `bootstrap/app.php`.
- **Server-authoritative** — total harga, ongkir, dan stok dihitung/divalidasi server.
- **`DB::transaction` + `lockForUpdate`** — mencegah double-booking (stok tak pernah minus).
- **Cache berlabel** (`app/Support/Cache/CacheHelper`) — katalog & laporan di-cache dan di-flush saat mutasi.

### Struktur folder inti
```text
app/
├── Enums/            # BookingStatus · PaymentStatus · DeliveryType · UserRole
├── Http/
│   ├── Controllers/Api/   # 10 controller tipis
│   ├── Requests/          # Validasi (Auth/ Booking/ Category/ Gear/)
│   └── Middleware/IsAdmin # Gerbang peran admin
├── Models/           # User · Gear · GearCategory · GearVariant · Booking · BookingItem · Payment · ActivityLog · Setting
├── Services/         # BookingService · GearService · CategoryService · PaymentService · ReportService · DeliveryFeeService
├── Support/Cache/    # CacheHelper (remember/flush + tag)
└── Providers/        # AppServiceProvider (rate limiter)
```

---

## 🌟 Fitur Backend

- 🔐 **Autentikasi** (Sanctum): register, login, profil, logout + **Role-Based Access** (`admin`/`customer`). Kolom `role` sengaja **tidak mass-assignable** (anti privilege escalation).
- 🔓 **Google OAuth** (Socialite): alur **code-exchange** — SPA menukar *one-time code* jadi token via `POST /auth/google/exchange`, token tak pernah muncul di URL.
- ⛺ **Katalog & filter gear**: pencarian (nama/deskripsi/brand), filter kategori (`slug`), rentang harga, sorting, paginasi. Detail gear **di-cache** (TTL 300s).
- 📦 **Booking engine transaksional**:
  - **Pessimistic locking** (`lockForUpdate`) pada gear/varian → stok aman saat pemesanan bersamaan.
  - **Ongkir dinamis** (`DeliveryFeeService`): *Pickup* = Rp 0; *Delivery* = **Rp 10.000** (mencakup ≤ 5 km & ≤ 5 kg) **+ Rp 1.000/km** di atas 5 km **+ Rp 1.000/kg** di atas 5 kg. Jarak dihitung **haversine** dari basecamp ke titik pada link Google Maps (resolusi short-link aman-SSRF).
  - Kode booking unik `KMB-YYYYMMDD-XXXX`, isolasi data antar-customer.
- 💳 **Midtrans Snap**:
  - Generate Snap URL + `Payment` (pending), expiry 24 jam.
  - **Webhook** dengan verifikasi **tanda tangan SHA-512 (fail-closed)** + cek kecocokan nominal.
  - `settlement`/`capture` → booking `confirmed`; `expire`/`cancel`/`deny` → booking `cancelled` + **stok dikembalikan** (idempoten).
- 🔁 **Handover & return**: stempel `picked_up_at`/`returned_at` saat transisi status + penanda pengembalian kartu jaminan (`identity-returned`).
- 🕓 **Activity log (audit trail)**: `booking.created`, `status.changed`, `payment.paid/failed`, `identity.verified/returned` — dibaca per-booking maupun feed admin.
- 📊 **Laporan & analitik admin**: dashboard (omset, total booking, sewa aktif, total gear), gear populer, revenue harian, stok menipis, periode ramai, sebaran status, performa kategori.
- 🚦 **Rate limiter bernama**: bucket `api` / `auth` / `google` / `delivery` terpisah.

---

## 🗄️ Skema Database

Strategi **PK hibrida**: transaksi finansial memakai **UUID** (aman dari enumerasi/IDOR), data master memakai **BIGINT**.

| Tabel | PK | Catatan |
|---|---|---|
| `users` | **UUID** | `role` enum, `google_id` (OAuth) |
| `bookings` | **UUID** | `booking_code` unik, field delivery/jaminan/handover, enum `status` & `delivery_type` |
| `payments` | **UUID** | 1-1 ke booking, `gateway`, `external_id`, `status`, `paid_at` |
| `gear_categories` | BIGINT | nama & slug unik |
| `gears` | BIGINT | **soft delete**, slug (spatie), **FULLTEXT** (name/description/brand) |
| `gear_variants` | BIGINT | stok per-varian |
| `booking_items` | BIGINT | snapshot harga & `line_total` |
| `activity_logs` | BIGINT | audit trail (FK booking + actor) |
| `settings` | key-value | parameter ongkir (base/radius/per-km/per-kg/basecamp) |
| `personal_access_tokens` | BIGINT | Sanctum |

**Relasi:** kategori `1—N` gear · gear `1—N` varian · booking `1—N` item · booking `1—1` payment · user `1—N` booking · booking `1—N` activity log.

---

## 🔌 Ringkasan Endpoint

| Grup | Contoh |
|---|---|
| **Publik** | `POST /register` · `POST /login` · `GET /auth/google/redirect` · `POST /auth/google/exchange` · `GET /gears` · `GET /gears/{id}` · `GET /categories` · `POST /delivery/quote` · `POST /payments/webhook` |
| **Customer** (`auth:sanctum`) | `GET /profile` · `POST /logout` · `POST /bookings` · `GET /bookings` · `GET /bookings/{id}` · `POST /bookings/{id}/payment` |
| **Admin** (`is.admin`) | `POST\|PUT\|DELETE /admin/gears` (+varian) · `/admin/categories` · `GET /admin/bookings` · `PATCH /admin/bookings/{id}/status\|verify\|identity-returned` · `GET /admin/activity-logs` · `GET /admin/reports/*` · `/admin/settings/delivery` |

Referensi lengkap (request/response, auth, rate limit, lifecycle status) → **Swagger `/docs/api`** atau `GET /api` (peta endpoint).

---

## 🚀 Menjalankan Lokal

**Prasyarat:** PHP ≥ 8.3, Composer ≥ 2.5, ekstensi `pdo_mysql`/`pdo_sqlite`, `mbstring`, `openssl`.

```bash
git clone https://github.com/DannZ10/kembara-backend.git kembara-api
cd kembara-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- API: `http://localhost:8000/api` · Swagger: `http://localhost:8000/docs/api`
- Default `.env.example` memakai **SQLite** (jalan tanpa MySQL). Untuk MySQL, set `DB_CONNECTION=mysql` + kredensial DB.
- Midtrans & Google OAuth opsional untuk lokal — biarkan placeholder `TESTKEY` agar berjalan offline.

---

## ☁️ Deployment (Render + Aiven)

Repo sudah **Dockerized**. Backend berjalan di **Render** (Docker) dengan database **Aiven MySQL** (TLS).

- `Dockerfile` (PHP 8.3-cli, `pdo_mysql`), `docker/entrypoint.sh` (tunggu DB → **migrate + seed** saat boot → bind `$PORT`), `docker/php.ini` (`variables_order=EGPCS` agar env PaaS terbaca).
- `bootstrap/app.php` memakai `trustProxies` → skema **https** benar di balik proxy Render (URL absolut + Swagger).
- `config/database.php` mendukung **`DB_SSL=true`** → koneksi TLS ke MySQL managed tanpa file CA.

### Variabel environment kunci
```env
APP_NAME=Kembara.id
APP_ENV=production
APP_KEY=base64:...            # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://<backend>.onrender.com

FRONTEND_URL=https://<frontend>.vercel.app   # untuk CORS

DB_CONNECTION=mysql
DB_HOST=... DB_PORT=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...
DB_SSL=true                   # untuk MySQL managed yang mewajibkan TLS (Aiven)

MIDTRANS_SERVER_KEY=SB-Mid-server-...
MIDTRANS_CLIENT_KEY=SB-Mid-client-...
MIDTRANS_IS_PRODUCTION=false

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://<backend>.onrender.com/api/auth/google/callback
```

> **Catatan:** setelah deploy, daftarkan `GOOGLE_REDIRECT_URI` di Google Cloud Console, dan set **Payment Notification URL** Midtrans ke `https://<backend>/api/payments/webhook`.

---

## 🧪 Pengujian

```bash
php artisan test
```

Cakupan **PHPUnit** (Auth, Gear, Booking, Payment & Report): register/login/profil/logout, katalog + CRUD admin, booking pickup + gagal saat stok kurang + isolasi antar-customer + restore stok saat cancel, buat Snap URL + webhook settlement/expire + laporan admin.

### End-to-end (Postman / Newman)
```bash
npx newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" --ignore-redirects
```
Menelusuri alur penuh (katalog → auth → booking → payment → handover → audit → reports) dengan assertion tiap response, termasuk uji negatif (401/403) & entrypoint OAuth (302). Suite mengembalikan booking uji ke `returned` sehingga **stok otomatis pulih** — aman dijalankan berulang.

---

## 📖 Dokumentasi

- **Swagger (live)** — endpoint interaktif: https://kembara-backend.onrender.com/docs/api
- **Referensi & laporan** — `API_DOCUMENTATION.md`, `TESTING.md`, koleksi `postman/`.

---

## 🔑 Kredensial Demo

- **Admin**: `admin@kembara.com` / `admin123`
- **Customer**: `customer@kembara.com` / `customer123`
