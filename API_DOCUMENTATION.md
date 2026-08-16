# GearNest API Documentation

Rental & booking platform for outdoor gear. Laravel 12 REST API with token
authentication (Laravel Sanctum) and role-based authorization (admin / customer).

- **Base URL (local):** `http://127.0.0.1:8000/api`
- **Format:** JSON (send `Accept: application/json` on every request)
- **Auth:** Bearer token — `Authorization: Bearer <token>`
- **Database:** MySQL (`gearnest_db`)
- **Postman:** import the `GearNest API` collection + `GearNest — Local` environment (also in the `GearNest API` Postman workspace).

Opening `GET /api` in a browser returns a live index of all endpoints.

---

## Authentication & Roles

Authentication uses **Laravel Sanctum** personal access tokens. Register or log
in to receive a token, then send it as a Bearer header on protected routes.

| Role | Access |
|------|--------|
| `customer` | Public catalog + own bookings & payments. Default role on registration. |
| `admin` | Everything above **plus** gear/category CRUD, all bookings, and reports. Seeded only (no public admin signup). |

Admin routes are guarded by the `is.admin` middleware (`app/Http/Middleware/IsAdmin.php`), which returns `403` for non-admins.

**Seeded test accounts** (`database/seeders/UserSeeder.php`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@gearnest.com` | `admin123` |
| Customer | `customer@gearnest.com` | `customer123` |

---

## Response Conventions

**Success envelope**

```json
{ "success": true, "message": "optional string", "data": { }, "meta": { } }
```

`meta` appears on paginated lists:

```json
"meta": { "current_page": 1, "last_page": 4, "per_page": 10, "total": 33 }
```

**Error responses**

| Code | Meaning | Body |
|------|---------|------|
| `401` | Not authenticated (missing/invalid token) | `{ "message": "Unauthenticated." }` |
| `403` | Authenticated but not admin | `{ "success": false, "message": "Forbidden. Admin access required." }` |
| `404` | Resource not found | `{ "message": "..." }` |
| `422` | Validation failed / business rule (e.g. insufficient stock) | `{ "message": "...", "errors": { } }` or `{ "success": false, "message": "..." }` |

**Pagination & filtering** — list endpoints accept `page` and `limit`. Gears and
bookings additionally accept `search`, and date-range filters `date_from` /
`date_to` (`YYYY-MM-DD`). Gears also accept `category` (slug), `sort_by`
(`price_per_day|created_at|name|stock_available`) and `sort_order` (`asc|desc`).

---

## Data Model (ERD)

Six domain tables. Primary keys: `users`, `bookings`, `payments` use **UUID**;
`gear_categories`, `gears`, `booking_items` use auto-increment integers.

```
users (uuid)
  └─< bookings (uuid)                       user_id  → users.id
         ├─< booking_items (id)             booking_id → bookings.id
         │        gear_id → gears.id
         └─1 payment (uuid)                 booking_id → bookings.id (unique)

gear_categories (id)
  └─< gears (id)                            category_id → gear_categories.id
         └─< booking_items (id)
```

| Table | Key columns | Relationships |
|-------|-------------|---------------|
| `users` | id, name, email, password, phone, role | hasMany `bookings` |
| `gear_categories` | id, name, slug, description | hasMany `gears` |
| `gears` | id, category_id, name, slug, price_per_day, stock_total, stock_available, is_available | belongsTo `category`, hasMany `booking_items` |
| `bookings` | id, user_id, booking_code, start_date, end_date, duration_days, delivery_*, subtotal, total_price, status, identity_verified | belongsTo `user`, hasMany `items`, hasOne `payment` |
| `booking_items` | id, booking_id, gear_id, quantity, price_per_day, line_total | belongsTo `booking`, belongsTo `gear` |
| `payments` | id, booking_id, gateway, external_id, amount, status, paid_at | belongsTo `booking` |

**Enums:** `UserRole` (admin, customer) · `BookingStatus` (pending, confirmed,
active, returned, cancelled) · `DeliveryType` (pickup, delivery) ·
`PaymentStatus` (pending, paid, expired, failed).

> Theme mapping (Guideline theme 2 — *Sistem Booking & Manajemen Layanan*):
> **Layanan/Service** = `gears` (+ `gear_categories`); **Jadwal/Schedule &
> availability** = booking `start_date`/`end_date` validated against
> `stock_available`; **Transaksi** = `bookings` + `booking_items` + `payments`.

---

## Endpoints

### Auth

#### `POST /api/register`
Public. Creates a customer account, returns a token.

Request:
```json
{ "name": "Rizal", "email": "rizal@mail.com", "password": "password123", "password_confirmation": "password123", "phone": "08123456789" }
```
Response `201`:
```json
{ "success": true, "message": "Registrasi akun berhasil", "data": { "token": "1|abc...", "user": { }, "role": "customer" } }
```

#### `POST /api/login`
Public. Returns a token.

Request: `{ "email": "customer@gearnest.com", "password": "customer123" }`
Response `200`: `{ "success": true, "data": { "token": "...", "user": { }, "role": "customer" } }`
Wrong credentials → `401 { "success": false, "message": "Email atau password tidak sesuai." }`

#### `GET /api/profile` · `POST /api/logout`
Auth required. Profile returns the current user; logout revokes the current token.

### Public Catalog

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/gears` | Paginated. Query: `search`, `category`, `min_price`, `max_price`, `sort_by`, `sort_order`, `date_from`, `date_to`, `page`, `limit` |
| GET | `/api/gears/{id}` | Single gear (numeric id) |
| GET | `/api/gears/slug/{slug}` | Single gear by slug |
| GET | `/api/categories` | All categories with `gears_count` |
| GET | `/api/categories/{id}` | Category with its gears |

`GET /api/gears?search=tenda&category=tenda&sort_by=price_per_day&sort_order=asc&limit=8`
```json
{ "success": true, "data": [ { "id": 1, "name": "...", "price_per_day": "85000.00", "stock_available": 5, "category": { } } ], "meta": { "current_page": 1, "last_page": 4, "per_page": 8, "total": 33 } }
```

### Customer — Bookings & Payment (auth)

#### `POST /api/bookings`
Creates a booking. Stock is checked and decremented under a DB transaction with
pessimistic lock (prevents overbooking). Delivery fee is computed server-side.

Request:
```json
{
  "items": [{ "gear_id": 1, "quantity": 2 }],
  "start_date": "2026-08-20",
  "end_date": "2026-08-23",
  "delivery_type": "pickup",
  "notes": "optional"
}
```
For `delivery_type: "delivery"` also send `delivery_address` and `delivery_distance_km` (0.1–30).
Response `201`: booking with `items`, `booking_code`, computed `subtotal`/`total_price`, `status: "pending"`.
Insufficient stock → `422 { "success": false, "message": "Stok gear '...' tidak mencukupi ..." }`.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/bookings` | Own bookings. Query: `status`, `date_from`, `date_to`, `page`, `limit` |
| GET | `/api/bookings/{id}` | Own booking (404 if not owner) |
| POST | `/api/bookings/{id}/payment` | Create/return Midtrans Snap payment URL |
| GET | `/api/bookings/{id}/payment` | Payment status |

### Admin — Gear & Category CRUD (admin)

| Method | Path | Body |
|--------|------|------|
| POST | `/api/admin/gears` | `category_id`, `name`, `price_per_day`, `stock_total`, `brand?`, `description?`, `image_url?`, `weight_kg?`, `is_available?` |
| PUT | `/api/admin/gears/{id}` | Any of the above |
| DELETE | `/api/admin/gears/{id}` | Soft-deactivates (`is_available=false`), keeps history |
| POST | `/api/admin/categories` | `name` (unique), `description?`, `image_url?` |
| PUT | `/api/admin/categories/{id}` | `name`, `description?` |
| DELETE | `/api/admin/categories/{id}` | `422` if the category still has gears |

### Admin — Bookings (admin)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/admin/bookings` | All bookings. Query: `search` (code/customer), `status`, `date_from`, `date_to`, `page`, `limit` |
| PATCH | `/api/admin/bookings/{id}/status` | Body `{ "status": "confirmed" }`. Cancel/return restores stock |
| PATCH | `/api/admin/bookings/{id}/verify` | Body `{ "verified": true }` (toggle identity guarantee) |

### Admin — Reports & Analytics (admin)

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/admin/reports/dashboard` | Totals: revenue, bookings, active rentals, gears, pending |
| GET | `/api/admin/reports/popular-gear` | Most-rented gears (`limit`) |
| GET | `/api/admin/reports/revenue` | Revenue per period. Query: `groupBy=daily\|monthly`, `date_from`, `date_to` |
| GET | `/api/admin/reports/low-stock` | Gears at/under threshold (`threshold`, default 3) |
| GET | `/api/admin/reports/busiest-periods` | Busiest rental dates ("jadwal paling ramai") |
| GET | `/api/admin/reports/status-breakdown` | Booking count + value per status |
| GET | `/api/admin/reports/category-performance` | Units rented + revenue per category |

### Webhook

`POST /api/payments/webhook` — Midtrans notification callback (SHA512 signature
verified when a real server key is configured). Updates payment + booking status.

---

## Running Locally

```bash
cd gearnest-api
composer install
cp .env.example .env        # then set DB_* for MySQL gearnest_db
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve           # http://127.0.0.1:8000
```

Test suite: `php artisan test` (Feature tests cover auth, gear CRUD, booking
stock/cancel flow, identity-verify toggle, payments & reports).

## Request Flow

Every feature follows: **route → middleware (`auth:sanctum` / `is.admin`) →
FormRequest (validation) → Controller (thin) → Service (business logic) →
Model / DB**. Controllers never hold business logic; services own transactions,
stock locking, and fee/period calculations.
