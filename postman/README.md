# Kembara.id API — Postman / Newman Test Automation

An automated end-to-end test suite for the Kembara.id API. The collection walks the full rental lifecycle — catalog browsing, auth, booking, payment, admin handover, audit trail, reports — and asserts on every response, including authorization negatives.

## Files

| File | Purpose |
|---|---|
| `Kembara.id.postman_collection.json` | The request collection with test scripts (Postman Collection v2.1). |
| `Kembara.id.local.postman_environment.json` | Base URL + demo credentials for a local server. |

## Prerequisites

- API running locally: `php artisan serve` (default `http://127.0.0.1:8000`).
- Database seeded (demo admin + customer accounts, catalog): `php artisan migrate:fresh --seed`.
- Node.js 18+ (for the Newman CLI runner).

## Run with Newman (CLI automation)

No install needed — run it straight through `npx`:

```bash
npx newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" \
  --ignore-redirects
```

`--ignore-redirects` matters: the `GET /auth/google/redirect` test asserts the `302` redirect itself rather than following it to the frontend.

### HTML report (optional)

```bash
npx newman run "postman/Kembara.id.postman_collection.json" \
  -e "postman/Kembara.id.local.postman_environment.json" \
  --ignore-redirects \
  -r cli,htmlextra
```

(`npx newman run ... -r htmlextra` pulls `newman-reporter-htmlextra` on demand; the report lands in `newman/`.)

## Run in the Postman app

1. **Import** both JSON files (Import → Files).
2. Select the **Kembara.id — Local** environment (top-right).
3. Open the collection → **Run** to launch the Collection Runner.

## How it flows

Requests are ordered so they chain — each folder feeds the next through collection variables, so the suite must run top-to-bottom (Newman does this by default):

| # | Folder | What it proves |
|---|---|---|
| 0 | Health | `GET /api` index responds and self-identifies. |
| 1 | Public Catalog | Gears + categories are listable; captures a bookable `gear_id`, `gear_slug`, `category_id`. |
| 2 | Auth | Register (issues a **customer**-role token — no privilege escalation), login as customer + admin, profile. Captures `customer_token`, `admin_token`. |
| 3 | Customer Booking Flow | Creates a pickup booking (dynamic dates), lists/reads it, requests a Midtrans Snap payment, reads payment status. Captures `booking_id`. |
| 4 | Admin Ops | Status → `active` (stamps `picked_up_at`) → `returned` (stamps `returned_at`, restores stock), verify identity guarantee, mark identity returned, read the activity/audit trail, create + delete a temp category, read the reports dashboard. |
| 5 | Security / AuthZ | Negatives: no-token → `401`, customer hitting an admin route → `403`, OAuth entrypoint → `302`. |
| 6 | Logout | Revokes the customer token. |

### Notes

- **Runtime variables** (`customer_token`, `booking_id`, ...) are written by test scripts — leave them blank in the environment.
- The **payment** request hits the live Midtrans gateway, so it tolerates `200` (Snap URL created), `422` (business error, e.g. keys absent), and `500` (gateway unreachable/timeout from the test host — environmental, not an API defect). It only asserts `payment_url` when the response is `200`. On a host without outbound access to Midtrans this call can take ~30s before timing out; that latency is the gateway, not the API.
- The booking created by the run is driven to `returned`, which **restores the gear stock it consumed** — running the suite repeatedly does not drain the catalog. The temp category it creates is deleted in the same run.
