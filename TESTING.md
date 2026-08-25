# Kembara.id API — Testing Report

Two layers of automated tests guard the API:

1. **Feature/unit tests** (`php artisan test`) — PHPUnit, in-process, database mocked/seeded per test.
2. **End-to-end API tests** (Postman + Newman) — real HTTP against a running server, asserting the full rental lifecycle including live payment and authorization negatives.

Last run: **2026-08-25** · API `1.0.0` · local server `http://127.0.0.1:8000`.

---

## 1. Feature / Unit Tests — PHPUnit

```bash
php artisan test
```

**Result: `25 passed` (87 assertions).**

| Suite | Covers |
|---|---|
| `AuthTest` | register, login + token, profile, logout |
| `GearTest` | list/filter/search, detail by slug, admin create/update/delete |
| `BookingTest` | pickup booking, insufficient-stock rejection, cross-user isolation, cancel restores stock |
| `PaymentAndReportTest` | Snap URL creation, webhook settlement → confirmed, webhook expire → stock restored, admin reports |

Midtrans is mocked here, so these run offline and deterministically.

---

## 2. End-to-End API Tests — Postman / Newman

```bash
# API running + database seeded first
npx newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" \
  --ignore-redirects
```

Collection: [`postman/Kembara.id.postman_collection.json`](postman/Kembara.id.postman_collection.json) · runner guide: [`postman/README.md`](postman/README.md).

### Result summary

| Metric | Value |
|---|---:|
| Requests | **28** |
| Test scripts | **28** |
| Assertions | **69** |
| **Failed** | **0** |
| Duration | ~56 s |

> The suite runs top-to-bottom; each folder captures tokens/ids into collection variables that later folders consume.

### Coverage by folder

| # | Folder | Requests | What it proves | Result |
|---|---|:--:|---|:--:|
| 0 | Health | 1 | `GET /api` responds and self-identifies | ✅ |
| 1 | Public Catalog | 5 | gears + categories listable; captures a bookable gear/slug/category | ✅ |
| 2 | Auth | 4 | register issues a **customer**-role token (no escalation); login customer + admin; profile | ✅ |
| 3 | Customer Booking Flow | 5 | create pickup booking, list/read it, Midtrans Snap payment, payment status | ✅ |
| 4 | Admin Ops | 9 | status → `active` (stamps `picked_up_at`) → `returned` (stamps `returned_at`, restores stock), verify identity, mark identity returned, audit trail, category create+delete, reports dashboard | ✅ |
| 5 | Security / AuthZ | 3 | no-token → `401`, customer on admin route → `403`, OAuth entrypoint → `302` | ✅ |
| 6 | Logout | 1 | token revoked | ✅ |

### What the negatives confirm (security)

- **Authentication** — `GET /profile` without a token returns `401`.
- **Authorization** — a customer token on `GET /admin/bookings` returns `403` (the `is.admin` guard holds).
- **No privilege escalation** — `POST /register` always returns a `customer` role even though `role` is not in the request contract.
- **OAuth entrypoint** — `GET /auth/google/redirect` returns a `302` (to Google when configured, or back to the SPA with `?error=google_not_configured` when not).

### Notes

- **Payment** hits the live Midtrans gateway. The assertion accepts `200` (Snap URL created), `422` (business error), or `500` (gateway unreachable/timeout from the test host) so the suite stays green regardless of gateway reachability; `payment_url` is asserted only on `200`.
- **Idempotent** — the booking created during the run is driven to `returned`, which restores the stock it consumed, and the temp category is deleted in the same run. Re-running does not drain the catalog.
- **Rate limits** — auth endpoints use a dedicated `auth` rate-limiter bucket (10/min), isolated from the general `api` bucket (120/min), so ordinary catalog traffic cannot lock out login.

### Regenerate an HTML report (optional)

```bash
npm i -g newman newman-reporter-htmlextra
newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" \
  --ignore-redirects -r htmlextra \
  --reporter-htmlextra-export postman/newman/report.html
```

---

## Demo credentials

- **Admin:** `admin@kembara.com` / `admin123`
- **Customer:** `customer@kembara.com` / `customer123`
