# Kembara.id API Documentation

RESTful API for the Kembara.id outdoor-gear rental platform. Laravel 11, token auth via **Laravel Sanctum**, payments via **Midtrans Snap**.

- **Base URL (local):** `http://localhost:8000/api`
- **Version:** `1.0.0`
- **Automated tests:** see [`postman/`](postman/README.md) — a Newman/Postman suite covering every flow below.

---

## Conventions

### Response envelope

Every JSON response follows one shape:

```json
{
  "success": true,
  "message": "optional human-readable string",
  "data": {},
  "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 24 }
}
```

`meta` is present only on paginated list endpoints. On failure, `success` is `false` and `message` explains why; internal exception details are never leaked (unless `APP_DEBUG=true`).

### Authentication

Protected endpoints require a Bearer token issued by `POST /login` or `POST /register`:

```
Authorization: Bearer <token>
Accept: application/json
```

- Tokens are opaque Sanctum tokens; send `Accept: application/json` on every request so auth failures return `401` JSON instead of a redirect.
- **Idle timeout:** tokens expire after 30 minutes of inactivity.
- **Roles:** `admin` and `customer`. Admin-only routes sit under `/api/admin/*` and are guarded by the `is.admin` middleware (returns `403` for customers).

### Rate limits

| Scope | Limit |
|---|---|
| All API routes | 120 req/min per IP |
| `POST /register`, `POST /login` | 10 req/min per IP |
| `GET /auth/google/*` | 30 req/min per IP |
| `POST /delivery/quote` | 60 req/min per IP |

### Status codes

`200` OK · `201` Created · `401` Unauthenticated · `403` Forbidden (wrong role) · `404` Not found · `422` Validation / business-rule error · `429` Rate limited.

---

## Meta

### `GET /api`
Health check + machine-readable endpoint map. Public.

---

## Authentication

### `POST /register`
Public. Creates a **customer** account (role is server-assigned — a client cannot self-grant `admin`).

```json
{
  "name": "Andi Outdoor",
  "email": "andi@example.com",
  "password": "Password123",
  "password_confirmation": "Password123",
  "phone": "081200000000",
  "address": "Bandung"
}
```
- `password`: min 8 chars, at least one letter and one number.
- **201** → `data: { token, user, role }`.

### `POST /login`
Public.

```json
{ "email": "customer@kembara.com", "password": "customer123" }
```
- **200** → `data: { token, user, role }`; **401** on bad credentials.

### `GET /profile`  🔒
Returns the authenticated user.

### `POST /logout`  🔒
Revokes the current token.

### `GET /auth/google/redirect` · `GET /auth/google/callback`
Google OAuth (browser full-page redirects, not XHR). `redirect` sends the user to Google; `callback` finds-or-creates the user, mints a Sanctum token, and redirects to `FRONTEND_URL/auth/callback?token=...`. When `GOOGLE_CLIENT_ID` is unset the entrypoint redirects back with `?error=google_not_configured` instead of erroring.

---

## Public Catalog

### `GET /gears`
Paginated gear catalog. Query params: `search`, `category` (slug), `min_price`, `max_price`, `sort`, `limit`, `page`.

### `GET /gears/{id}` · `GET /gears/slug/{slug}`
Single gear by numeric id or slug.

### `GET /categories` · `GET /categories/{id}`
All categories / one category.

### `POST /delivery/quote`
Public (throttled). Live delivery-fee preview. Distance is derived **server-side** from the pasted Google Maps link; weight is recomputed from `items` when supplied.

```json
{
  "delivery_type": "delivery",
  "delivery_maps_url": "https://maps.app.goo.gl/....",
  "items": [ { "gear_id": 1, "quantity": 2 } ]
}
```
- **200** → `data: { fee, distance_km, ... }`; **422** if the maps link has no readable location.

---

## Customer — Bookings & Payment  🔒

### `POST /bookings`
Creates a booking. Stock is deducted under a pessimistic lock; totals (subtotal + delivery fee) are computed server-side.

```json
{
  "items": [ { "gear_id": 1, "quantity": 1, "gear_variant_id": null } ],
  "start_date": "2026-09-01",
  "end_date": "2026-09-03",
  "delivery_type": "pickup",
  "delivery_address": null,
  "delivery_maps_url": null,
  "identity_type_1": "KTP",
  "identity_type_2": "SIM",
  "identity_agreed": true,
  "notes": "optional"
}
```
- `start_date` ≥ today; `end_date` > `start_date`.
- `delivery_type`: `pickup` | `delivery`. For `delivery`, `delivery_address` and `delivery_maps_url` are required.
- `identity_type_*`: one of `KTP`, `SIM`, `KTM`, `Paspor` (two guarantee IDs handed over at pickup/delivery).
- `identity_agreed` must be truthy.
- **201** → `data` is the booking with `booking_code` (`KMB-YYYYMMDD-XXXX`), items, totals, `status: pending`.

### `GET /bookings`
The caller's own bookings (paginated). Filters: `status`, `date_from`, `date_to`, `limit`.

### `GET /bookings/{id}`
One of the caller's bookings — includes `items`, `payment`, and `activities` (audit trail). Cross-user access returns `404`.

### `POST /bookings/{id}/payment`
Creates a Midtrans Snap payment for the booking.
- **200** → `data: { payment_url, external_id, amount, status, expired_at }`.

### `GET /bookings/{id}/payment`
Current payment record for the booking.

---

## Payment Webhook

### `POST /payments/webhook`
Public Midtrans callback. The signature is verified with SHA512 (`hash_equals`). A `settlement`/`capture` marks the payment `paid` and the booking `confirmed`; an `expire`/`cancel` cancels the booking and **restores gear stock**.

---

## Admin  🔒 `is.admin`

### Gear management
| Method | Path | Purpose |
|---|---|---|
| `POST` | `/admin/gears` | Create gear |
| `PUT` | `/admin/gears/{id}` | Update gear |
| `PATCH` | `/admin/gears/{id}/availability` | Toggle availability |
| `DELETE` | `/admin/gears/{id}` | Hard delete |

`POST /admin/gears` body: `category_id`*, `name`*, `price_per_day`*, `stock_total`*, `description`, `brand`, `image_url`, `images[]`, `weight_kg`, `is_available` (\* required).

### Gear variants (size/colour + per-variant stock)
`POST /admin/gears/{gearId}/variants` · `PUT /admin/gears/{gearId}/variants/{variantId}` · `DELETE /admin/gears/{gearId}/variants/{variantId}`

### Categories
`POST /admin/categories` · `PUT /admin/categories/{id}` · `DELETE /admin/categories/{id}`. Body: `name`* (unique), `description`, `image_url`.

### Delivery settings
`GET /admin/settings/delivery` · `PUT /admin/settings/delivery`

### Booking management & handover
| Method | Path | Purpose |
|---|---|---|
| `GET` | `/admin/bookings` | All bookings (filters: `status`, `search`, `date_from`, `date_to`, `limit`) |
| `PATCH` | `/admin/bookings/{id}/status` | Change status — see lifecycle below |
| `PATCH` | `/admin/bookings/{id}/verify` | `{ "verified": true }` — mark the identity guarantee verified |
| `PATCH` | `/admin/bookings/{id}/identity-returned` | `{ "returned": true }` — mark the guarantee IDs handed back to the customer |

`PATCH .../status` body: `{ "status": "confirmed" | "active" | "returned" | "cancelled" }`.

### Audit trail
`GET /admin/activity-logs` — paginated activity feed. Filters: `action`, `search`, `limit`.

### Reports & analytics
`GET /admin/reports/` + `dashboard` · `popular-gear` · `revenue` · `low-stock` · `busiest-periods` · `status-breakdown` · `category-performance`.

---

## Booking status lifecycle

```
pending ──(payment settled / admin)──▶ confirmed ──▶ active ──▶ returned
   │                                                              
   └────────────────────── cancelled ◀───────────────────────────
```

- **active**: stamps `picked_up_at` the first time it is reached (gear handed over).
- **returned**: stamps `returned_at` and **restores stock**.
- **cancelled**: restores stock (if not already returned/cancelled).

## Activity-log actions

Every meaningful change is recorded for the audit trail (`GET /admin/activity-logs`, and per-booking under `activities`):

| Action | When |
|---|---|
| `booking.created` | A booking is placed |
| `status.changed` | Booking status transitions |
| `payment.paid` / `payment.failed` | Midtrans webhook result |
| `identity.verified` | Admin verifies the guarantee IDs |
| `identity.returned` | Admin hands the guarantee IDs back |

---

## Demo credentials

- **Admin:** `admin@kembara.com` / `admin123`
- **Customer:** `customer@kembara.com` / `customer123`
