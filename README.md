# 🏕️ Kembara.id API — Outdoor Rental Management System (Backend)

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![Sanctum](https://img.shields.io/badge/Auth-Sanctum-red?style=for-the-badge)](https://laravel.com/docs/sanctum)
[![Midtrans](https://img.shields.io/badge/Payment-Midtrans_Snap-blue?style=for-the-badge)](https://midtrans.com)
[![Tests](https://img.shields.io/badge/Tests-25%2F25_Passed-brightgreen?style=for-the-badge)](https://phpunit.de)

**Kembara.id API** adalah sistem RESTful API backend untuk platform persewaan alat outdoor & peralatan gunung. Dibangun menggunakan **Laravel 11**, arsitektur **Hybrid UUID + ID**, **Pessimistic Stock Locking**, dan integrasi payment gateway **Midtrans Snap**.

---

## 🌟 Fitur Utama Backend

- 🔐 **Otentikasi & Profil (`Sanctum`)**: Register, Login, Profile, Logout, dan Role-Based Access Control (`admin` / `customer`).
- ⛺ **Katalog & Filter Gear**: Pencarian kata kunci, filter kategori (`slug`), filter rentang harga, pengurutan, dan paginasi.
- 📦 **Booking Engine Transaksional**:
  - **Pessimistic Locking (`lockForUpdate()`)** untuk menjamin stok tidak minus saat pemesanan berbarengan.
  - Perhitungan biaya antar dinamis via `DeliveryFeeService` (Pickup Rp 0; Delivery Rp 10.000 untuk ≤5km & ≤5kg, lalu +Rp 1.000 per km/kg — jarak dihitung dari link Google Maps).
  - Penjanaan kode booking unik (`KMB-YYYYMMDD-XXXX`).
  - Isolasi data transaksi antar pengguna (*Customer Data Isolation*).
- 💳 **Midtrans Snap Payment Integration**:
  - Generate Snap Token & Snap Redirect URL otomatis.
  - Webhook Callback Handler dengan verifikasi SHA512 signature.
  - Penanganan otomatis status `PAID` ➔ Booking `CONFIRMED`.
  - Penanganan otomatis status `EXPIRED`/`CANCELLED` ➔ Pembatalan booking & **Pengembalian Stok Gear Otomatis**.
- 📊 **Laporan & Analytics Admin**: Dashboard summary (omset, total booking, sewa aktif, alert stok menipis ≤ 3 unit, dan ranking gear terpopuler).
- 🔁 **Handover & Return Tracking**: Stempel otomatis `picked_up_at` / `returned_at` saat transisi status, plus penanda pengembalian kartu jaminan identitas (`identity-returned`).
- 🕓 **Activity Log (Audit Trail)**: Setiap aksi penting tercatat — `booking.created`, `status.changed`, `payment.paid` / `payment.failed`, `identity.verified`, `identity.returned` — dapat dibaca per booking maupun feed admin.
- 🔓 **Google OAuth (`Socialite`)**: Login / daftar via akun Google (stateless, token dikirim balik ke SPA).
- 🚦 **Named Rate Limiters**: Bucket `api` / `auth` / `google` / `delivery` terpisah, sehingga limit login (10/menit) tidak terkuras oleh trafik katalog biasa.

---

## 🗄️ Arsitektur Database (Hybrid UUID + ID)

| Tabel | Primary Key | Role / Keamanan |
|---|---|---|
| `users` | `UUID` | Privasi user & keamanan token |
| `bookings` | `UUID` | Transaksi utama, mencegah IDOR |
| `payments` | `UUID` | Integrasi aman finansial Midtrans |
| `gears` | `BIGINT` | Performa join katalog & pencarian fast B-Tree, publik via `slug` |
| `gear_categories` | `BIGINT` | Referensi 14 kategori, publik via `slug` |
| `booking_items` | `BIGINT` | Tabel pivot/rincian item sewa |
| `personal_access_tokens` | `BIGINT` | Sanctum API Token Manager |

---

## 🚀 Panduan Instalasi & Menjalankan Lokal

### Prerequisites
- PHP >= 8.2
- Composer >= 2.5
- Extension PHP: `pdo_sqlite` / `pdo_mysql`, `mbstring`, `openssl`

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/DannZ10/kembara-backend.git kembara-api
cd kembara-api
composer install
```

### 2. Konfigurasi Environment File
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Eksekusi Migrasi & Seeder Database
```bash
php artisan migrate:fresh --seed
```

### 4. Jalankan Server API & Dokumentasi
```bash
php artisan serve
```
- **API Base URL**: `http://localhost:8000/api`
- **Interactive API Documentation (Scramble)**: `http://localhost:8000/docs/api`

---

## 🧪 Pengujian Otomatis (Automated Unit & Feature Tests)

```bash
php artisan test
```

```text
PASS  Tests\Feature\Api\AuthTest
✓ user can register
✓ user can login and receive token
✓ user can fetch profile
✓ user can logout

PASS  Tests\Feature\Api\GearTest
✓ anyone can list gears with search and filter
✓ anyone can view gear detail by slug
✓ admin can create gear
✓ admin can update gear
✓ admin can delete gear

PASS  Tests\Feature\Api\BookingTest
✓ customer can create pickup booking
✓ booking fails when stock insufficient
✓ customer cannot view other customers booking
✓ cancelling booking restores stock

PASS  Tests\Feature\Api\PaymentAndReportTest
✓ can create payment snap url for booking
✓ webhook settlement marks payment paid and booking confirmed
✓ webhook expire restores gear stock
✓ admin can view reports

Tests:    25 passed (87 assertions)
```

> Ringkasan di atas menampilkan test inti; total **25 test / 87 assertion** hijau (`php artisan test`). Alur end-to-end lengkap diuji via Postman/Newman (lihat bawah).

---

## 🤖 API Testing Automation (Postman / Newman)

End-to-end test suite yang menelusuri seluruh alur (katalog → auth → booking → payment → handover admin → audit trail → reports) lengkap dengan assertion di tiap response, termasuk uji otorisasi negatif (401/403) dan entrypoint OAuth (302).

```bash
# API harus berjalan + database ter-seed lebih dulu
npx newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" \
  --ignore-redirects
```

- Koleksi & environment ada di [`postman/`](postman/) — panduan lengkap: [`postman/README.md`](postman/README.md).
- Bisa juga di-import ke aplikasi Postman (Import → pilih kedua file JSON → jalankan Collection Runner).
- Suite mengembalikan booking uji ke status `returned` sehingga **stok gear otomatis dipulihkan** — aman dijalankan berulang.

## 📖 Dokumentasi

- **Referensi API** — endpoint lengkap (envelope response, auth, rate limit, body request, lifecycle status booking, daftar aksi audit): [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md).
- **Laporan hasil testing** — ringkasan PHPUnit (25/25) + Newman end-to-end (69/69), coverage per folder: [`TESTING.md`](TESTING.md).

---

## 🔑 Kredensial Demo Cepat

- **Admin Account**: `admin@kembara.com` / `admin123`
- **Customer Account**: `customer@kembara.com` / `customer123`
