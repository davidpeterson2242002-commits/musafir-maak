# مسافر معاك (Musafir Maak)

Intercity carpooling + parcel (ارسالية) app for Sudan.

- `backend/` — Laravel 13 REST API + Filament 5 admin panel (PostgreSQL)
- `mobile/` — Flutter app (from M7)
- `docs/` — progress log and decisions

Product spec and build log live in the claude.ai Project (`musafir-maak-spec.md`, `dev-progress-log.md`); `docs/` mirrors them.

## Run the backend locally

Requirements: PHP 8.3+ (pgsql, intl, gd, zip), Composer, PostgreSQL 14+.

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env (DB_CONNECTION=pgsql), then:
php artisan migrate --seed
php artisan serve
```

- Admin panel: http://localhost:8000/admin — local seed account `admin@musafir.test` / `password`
  (production: create admins with `php artisan admin:create`).
- WhatsApp and push messages are written to `storage/logs/gateways-YYYY-MM-DD.log` (no API keys needed).
- Demo data for the verification queue (pending IDs and driver applications with placeholder photos):
  `php artisan db:seed --class=DemoSeeder`
- With `APP_ENV=local` and `OTP_EXPOSE_IN_RESPONSE=true`, `POST /api/v1/auth/otp` also returns `debug_code`.

## Tests

Tests run against a separate PostgreSQL database `musafir_test` (see `phpunit.xml`).

```bash
createdb musafir_test
php artisan test
```

## API (v1)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/api/v1/config` | – | Public settings, texts, vehicle types, parcel sizes |
| GET | `/api/v1/cities` | – | Enabled cities with state |
| GET | `/api/v1/routes?popular=1` | – | Routes incl. closed ones (with closure message) |
| POST | `/api/v1/auth/otp` | – | `{phone}` → sends code via WhatsApp |
| POST | `/api/v1/auth/verify` | – | `{phone, code, device_name?}` → `{token, is_new, user}` |
| POST | `/api/v1/auth/logout` | token | Revoke current token |
| GET / PUT | `/api/v1/me` | token | Profile; first PUT = setup (`name, gender, city_id, accept_terms`, optional `email, whatsapp_phone`) |
| POST | `/api/v1/me/photo` | token | Multipart `photo` |
| DELETE | `/api/v1/me` | token | Delete account |
| POST / DELETE | `/api/v1/devices` | token | Register/unregister FCM token `{token, platform}` |
| GET | `/api/v1/me/identity` | token | ID verification status, documents, last review (reasons/note) |
| POST | `/api/v1/me/identity/documents` | token | Multipart `kind` (selfie, passport, national_id_front, national_id_back) + `file` |
| POST | `/api/v1/me/identity/submit` | token | `{document_type: passport\|national_id}` |
| GET / POST | `/api/v1/driver` | token | Driver account (`data: null` if not started) / start registration |
| POST | `/api/v1/driver/documents` | token | Multipart `kind=driving_licence` + `file` |
| POST | `/api/v1/driver/submit` | token | `{id_document_type?, accept_terms}` — submits identity + licence + vehicles |
| POST | `/api/v1/driver/vehicles` | token | `{type, make, model, color, year?, plate_number, seats}` |
| PUT / DELETE | `/api/v1/driver/vehicles/{id}` | token | Edit / delete (draft or rejected only) |
| POST | `/api/v1/driver/vehicles/{id}/documents` | token | Multipart `kind` (vehicle_front/back/side/interior, vehicle_registration) + `file` |
| POST | `/api/v1/driver/vehicles/{id}/submit` | token | Submit a vehicle added after approval |

Document URLs in responses are signed and expire (`SIGNED_URL_MINUTES`, default 30).

Send `Accept: application/json` and `Authorization: Bearer <token>`. Phone numbers are accepted in any common
Sudanese format (`0912345678`, `+249…`, Arabic digits) and returned as E.164 (`+249912345678`).

## Business settings

Every business parameter lives in the `settings` table and is edited at **Admin → النظام → الإعدادات**.
In code: `settings()->int('booking.response_timeout_hours')`. The catalogue with defaults is
`app/Support/SettingsRegistry.php`; after adding a key there, run `php artisan settings:sync`
(adds new keys, never overwrites values set by admins).
