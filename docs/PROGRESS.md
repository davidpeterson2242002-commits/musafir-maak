# مسافر معاك — Development Progress & Decisions Log

Source of truth for the product: `claude/musafir-maak-spec.md`. This file tracks the build: plan, status, decisions. Every session reads this first and updates it at the end of each milestone.

## Status
- 2026-11-09: Development plan approved by the user (below).
- 2026-11-09 (session 2): M1 blocked — packagist.org refused by the network allowlist.
- 2026-11-09 (session 3): **M1 done.** Packagist reachable. 58 tests / 215 assertions passing. Code pushed to GitHub (`main`). **Next step: M2.**

### Where the code lives (important)
**GitHub: `davidpeterson2242002-commits/musafir-maak` (private), branch `main`.** This is the source of truth for code; each chat's workspace is wiped.
- Start of every session: attach the repo (add_repo, push access) and clone it to `/home/claude/musafir-maak`; set up the workspace (below); then `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed` and run `php artisan test`.
- The user has given standing permission to push any code to this repository. Commit per milestone (or logical step) and push to `main`.
- `docs/PROGRESS.md` in the repo mirrors this log (the claude.ai Project copy is `claude/dev-progress-log.md`).

Workspace setup that worked: `service postgresql start`; `su postgres -c "psql -c \"CREATE USER musafir WITH PASSWORD 'secret' SUPERUSER;\""`; `su postgres -c "createdb -O musafir musafir"` and the same for `musafir_test`. In `.env` set `DB_PASSWORD=secret`. phpunit.xml points at `musafir_test` on pgsql.

## Working agreement (from the user)
1. Build in milestones: backend + admin first, then Flutter on top of the working API.
2. Every business parameter comes from the `settings` table, editable in Filament. No hardcoded fees, timeouts or limits.
3. External services (WhatsApp, push, email, storage) sit behind interfaces with fake/log drivers, so everything runs locally without API keys.
4. Feature tests for core rules: booking expiry, fee calculation, freeze, parcel code (plus chat masking).
5. At the end of each milestone: report what's done, how to run/test it, and what's next. Update this log.
6. Ask the user only for decisions that are genuinely theirs; otherwise pick sensible defaults and record them below.

## Milestones
| # | Scope | Status |
|---|---|---|
| M1 | Laravel + Sanctum + Filament setup; phone OTP auth (log driver); users; settings table + typed cached accessor + Filament settings pages; states/cities/routes; admin users + audit log; gateway interfaces with fake/log drivers | **Done 2026-11-09** |
| M2 | Driver registration, vehicles, private document uploads (signed URLs), verification queue (drivers / vehicles / passenger IDs) | Next |
| M3 | Trips + points, search (filters, sort, nearby dates, closure msg), bookings, 12h-or-departure expiry + hourly reminders (scheduler), cancellations + late-cancel penalties, no-show + 24h auto-reversal, route alerts, search logging | — |
| M4 | Fee engine (percentage / per seat / per trip, per vehicle type, min/max, snapshot at acceptance), free bookings, ledger, payment methods, settlement requests, optional wallet, weekly cycle + balance limit + grace, auto-freeze/unfreeze | — |
| M5 | Parcels (sizes, payer, prohibited items ack, pickup photo, 4-digit code), chat with number masking + flagging, ratings + low-rating flag, reports + enforcement, blocks | — |
| M6 | Real providers (FCM, WhatsApp Cloud API, mail), per-event channel matrix in admin, notification log, basic broadcast | — |
| M7 | Flutter foundation: RTL Arabic theme, API client, auth/OTP, profile, offline cache | — |
| M8 | Passenger flows: home, search, trip details, ID step, booking, status, my bookings, rating, alerts | — |
| M9 | Parcels, chat, public profiles, reports | — |
| M10 | Driver mode: registration, post trip, requests, trip management, balance, payments | — |
| M11 | Polish, low-bandwidth pass, release build | — |

## M1 — what was built
**Stack:** Laravel 13.40, Filament 5.11, Sanctum, PostgreSQL 16, PHP 8.3. Admin UI in Arabic/RTL (APP_LOCALE=ar).

**API (`/api/v1`)**: `GET config` (public settings + vehicle types + parcel sizes), `GET cities`, `GET routes?popular=1` (includes closed routes with closure message), `POST auth/otp`, `POST auth/verify` → `{token, is_new, user}`, `POST auth/logout`, `GET/PUT me` (first PUT = profile setup: name, gender, city_id, accept_terms; optional email, whatsapp_phone), `POST me/photo`, `DELETE me`, `POST/DELETE devices`. Full table in README.md.

**Key code**
- `app/Support/SettingsRegistry.php` — catalogue of all 47 business settings (type, group, Arabic label, default, min/max, public flag). `settings()` helper / `App\Support\Settings` (typed, cached, flushed on save). `php artisan settings:sync` adds new keys without overwriting admin values.
- `app/Services/OtpService.php` — 6-digit code (configurable), hashed, TTL, max attempts, resend cooldown, hourly cap per phone; plus IP throttle (10/min). WhatsApp failure → 503 and the code is burned.
- `app/Support/Phone.php` — Sudanese number normalization to E.164 (+249, 9 digits starting with 9 or 1; accepts 0…, 249…, 00249…, Arabic digits).
- `app/Contracts` (WhatsAppGateway, PushGateway, OtpSender) + `app/Gateways` (Log*, Fake*, WhatsAppOtpSender). Driver via env `WHATSAPP_DRIVER` / `PUSH_DRIVER` (log | fake). Log driver writes to `storage/logs/gateways-*.log`.
- `app/Models/Concerns/Auditable` + `app/Services/AuditLogger` — create/update/delete logged with old/new values only when an admin is acting; custom actions via `AuditLogger::log('user.banned', $user, [...])`.
- Admin guard `admin` (separate `admins` table); `RestrictedToRoles` trait for Filament resources (closures in Filament are injected by parameter name: use `$query`, `$record`, `$state`, `$get`).
- Filament: Settings page (tabs per group, generated from the registry), Routes (add reverse direction action, closure message, max price, popular flag), Cities, States, Users (list/view/limited edit + suspend/ban/reactivate with reason), Admins, Audit log (read-only).
- Seeders: settings defaults, 18 states / 36 cities, 28 routes (14 pairs both ways; launch routes marked popular). Local admin `admin@musafir.test` / `password` (local env only); `php artisan admin:create` for real admins.

**Tests (58):** phone normalization; OTP request/verify (cooldown, hourly cap, attempts lockout, expiry, reuse, invalidation, banned, hashed storage, WhatsApp failure); profile setup/validation, gender lock, photo upload to private disk, device tokens, account delete + re-signup, suspension auto-lift; cities/routes availability and closure messages; public config; settings defaults/typing/cache/fallback/sync/audit; admin panel access per role, settings page save/validation/list field, route create + duplicate, ban/reactivate.

## Repo structure (monorepo)
```
musafir-maak/
├── README.md  how to run, API table, settings
├── backend/   Laravel API + Filament admin
│   app/Enums, Models (+Concerns/Auditable), Http/Controllers/Api/V1 (+Requests, Resources, Middleware),
│   Services (OtpService, AuditLogger), Contracts, Gateways, Support (Settings, SettingsRegistry, Phone),
│   Filament (Pages/ManageSettings, Resources/*, Concerns/RestrictedToRoles)
│   database/migrations + seeders (SettingsSeeder, GeoSeeder) + factories
│   lang/ar/validation.php, tests/{Unit,Feature/{Api,Admin}}
├── mobile/    Flutter app (from M7)
└── docs/      PROGRESS.md (this log)
```

## Database schema (approved)
Money = integer SDG (whole pounds). Statuses = PHP enums stored as strings.

**Identity & config** (built in M1)
- users: name?, phone (unique), whatsapp_phone, email?, gender, city_id, photo_path, status (active/suspended/banned), suspended_until, id_verification_status (none/pending/verified/rejected), rating_avg, rating_count, phone_verified_at, terms_accepted_at, last_seen_at, soft deletes
- otp_codes: phone, code_hash, expires_at, attempts, consumed_at, ip_address
- device_tokens: user_id, token (unique), platform, last_used_at
- admins: name, email, password, role (super_admin/reviewer/support/finance), is_active
- settings: key (unique), value (json), type, group, label
- audit_logs: admin_id, action, subject (morph), changes (json {field: {old, new}}), ip_address
- states (name_ar, name_en, enabled, sort); cities (state_id, name_ar, name_en, enabled, sort)
- routes (model `TravelRoute`): origin_city_id, destination_city_id (unique pair), enabled, max_price_per_seat?, closure_message?, is_popular, sort

**Verification**
- documents: owner (morph: user / driver_profile / vehicle), kind (selfie, id_front, id_back, licence, registration, vehicle_front/back/side/interior), path (private disk)
- verification_reviews: subject (morph), status (pending/approved/rejected/info_requested), reasons (json), note, reviewed_by, reviewed_at

**Drivers & trips**
- driver_profiles: user_id (unique), status (pending_review/active/rejected/suspended/frozen), free_bookings_remaining, balance_owed, wallet_balance, settlement_due_at, frozen_at, rating_avg, rating_count
- vehicles: driver_profile_id, type (car/boksi/minibus/bus/pickup), make, model, color, year?, plate_number, seats, status
- trips: driver_profile_id, vehicle_id, route_id, departs_at, est_arrival_at?, price_per_seat, seats_total, seats_available, luggage_large, luggage_small, extra_luggage_price?, accepts_parcels, parcel_price_small/medium/large, max_parcels, women_only, notes, status (scheduled/departed/completed/cancelled), cancel_reason, departed_at, completed_at, hidden_by_admin
- trip_points: trip_id, kind (pickup/dropoff), label, lat?, lng?, sort
- bookings: trip_id, passenger_id, seats, pickup_point_id, extra_bags, includes_family, message, status (pending/accepted/rejected/expired/cancelled_by_passenger/cancelled_by_driver/completed/no_show), price_total, fee_amount, fee_free, expires_at, last_reminded_at, accepted_at, cancelled_at, late_cancellation, no_show_at, no_show_disputed_at
- parcels: trip_id, sender_id, description, size, photo_path, receiver_name, receiver_phone, payer (sender/receiver), price, fee_amount, fee_free, delivery_code (encrypted), status (pending/accepted/picked_up/delivered/cancelled/disputed), expires_at, pickup_photo_path, picked_up_at, delivered_at, refusal_reason
- route_alerts: user_id, origin_city_id, destination_city_id, date?, active
- search_logs: city pair / route_id, date, results_count, user_id?

**Money**
- ledger_entries (append-only): driver_profile_id, type (fee/payment/wallet_topup/adjustment/refund), bucket (balance/wallet), amount (signed), balance_after, wallet_after, reference (morph), note, created_by_admin_id?
- payment_methods: name, account_name, account_number, instructions, agent_locations (json), enabled, sort
- settlement_requests: driver_profile_id, payment_method_id, purpose (settlement/wallet_topup), amount, reference_number, receipt_path, status (pending/confirmed/rejected), reviewed_by, reject_reason

**Trust & comms**
- conversations: subject (morph: booking/parcel); messages: conversation_id, sender_id, body, was_masked, flagged
- ratings: rater_id, ratee_id, subject (morph), role (as_driver/as_passenger), stars, comment, tags (json). Unique per rater+subject
- reports: reporter_id, reported_user_id, subject (morph), reason, details, priority, status (open/in_review/resolved), resolution_note, handled_by
- enforcement_actions: user_id, action (warn/suspend/ban/unban), until?, reason, admin_id
- user_blocks: blocker_id, blocked_id
- notification_logs: user_id/phone, event, channel, status, error

## Decisions log
- 2026-11-09: Monorepo (backend/, mobile/, docs/).
- 2026-11-09: PostgreSQL; Sanctum tokens for mobile auth; latest stable Laravel + Filament.
- 2026-11-09: Money stored as integer SDG (whole pounds).
- 2026-11-09: Admins in a separate `admins` table with a role column (sub-roles later).
- 2026-11-09: Thin notification layer (interfaces + log drivers) built in M1 because OTP needs WhatsApp; M6 adds real providers and the per-event channel matrix.
- 2026-11-09: Still-open business numbers (fee %, balance limit, grace period) are seeded as placeholder settings; the user decides them before launch.
- 2026-11-09: OTP uses a log driver in local development, with rate limiting (configurable).
- 2026-11-09: Booking expiry and reminders run as an idempotent scheduler job every minute.
- 2026-11-09: Parcel delivery code is stored encrypted (not hashed) because the sender must be able to view it.
- 2026-11-09 (M1): Placeholders seeded: seat fee 10% for every vehicle type, parcel fee 10%, balance limit 50,000 SDG, grace 24h before freeze, late cancellation = under 3h before departure, 3 late cancellations / 30 days → 7-day booking block. All marked "قيمة مبدئية" in admin.
- 2026-11-09 (M1): Khartoum, Omdurman and Bahri are one city "الخرطوم" so capital searches find every trip; the neighbourhood is a pickup point.
- 2026-11-09 (M1): Routes are auto-created on the first trip between two enabled cities (setting `routes.auto_create`, default on) so coverage is nationwide without seeding every pair; admin can disable any route. To be implemented in M3 trip posting.
- 2026-11-09 (M1): Model for `routes` is `TravelRoute` (avoids clash with the Route facade).
- 2026-11-09 (M1): Timestamps stored in UTC (app timezone UTC); display in Africa/Khartoum is a presentation concern.
- 2026-11-09 (M1): OTP is 6 digits by default (setting `otp.length`), TTL 10 min, 5 attempts, 60s resend, 5/hour per phone.
- 2026-11-09 (M1): Gender is set once by the user; later changes only by admin (women-only trips depend on it).
- 2026-11-09 (M1): Deleting an account = soft delete + tokens revoked. Signing up again with the same phone restores the row with a blank profile. TODO M3/M4: block deletion with active bookings or owed balance.
- 2026-11-09 (M1): Email and file storage use Laravel's own Mail and Filesystem abstractions (log mailer / local private disk with signed `temporaryUrl`), so no custom interfaces were needed for them.
- 2026-11-09 (M1): The Laravel skeleton's CLAUDE.md asks to install Laravel Boost; not installed (not requested by the user).
- 2026-11-09 (M1): All 36 seeded cities are enabled; the admin should review coverage (e.g. areas affected by the war) before launch.
- 2026-11-09: Code hosted on GitHub `davidpeterson2242002-commits/musafir-maak` (private). User granted standing permission to push to it.
