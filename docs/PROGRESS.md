# مسافر معاك — Development Progress & Decisions Log

Source of truth for the product: `claude/musafir-maak-spec.md`. This file tracks the build: plan, status, decisions. Every session reads this first and updates it at the end of each milestone.

## Status
- 2026-11-09: Development plan approved by the user (below).
- 2026-11-09 (session 2): M1 blocked — packagist.org refused by the network allowlist.
- 2026-11-09 (session 3): **M1 done.** Packagist reachable. 58 tests / 215 assertions passing. Code pushed to GitHub (`main`).
- 2026-11-09 (session 3, cont.): **M2 done.** 95 tests / 412 assertions passing. Pushed to `main`.
- 2026-11-09 (session 3, cont.): **M3 done.** 147 tests / 683 assertions passing. Pushed to `main`.
- 2026-11-09 (session 3, cont.): **M4 done.** 190 tests / 876 assertions passing. Pushed to `main`.
- 2026-11-09 (session 3, cont.): **M5 done.** 246 tests / 1131 assertions passing. Pushed to `main`.
- 2026-11-10 (session 4): **M6 done.** 280 tests / 1270 assertions passing. Pushed to `main`. 
- 2026-11-10 (session 4, cont.): **M7 code written, NOT yet compiled.** The workspace can't download the Flutter SDK or reach pub.dev, so `flutter analyze` / `flutter test` have never run on `mobile/`. The user is installing Flutter locally. **Next step: compile M7 on the user's machine (see "M7 — first compile checklist"), fix whatever the analyzer reports, then M8.**
- 2026-11-10 (session 4, cont.): **M8 code written on top of M7, also NOT compiled** (the user asked to continue while Flutter downloads). `mobile/` is now ~9,000 lines of Dart that have never been through `flutter analyze`. **Next step: first compile of M7+M8 together (checklist below) before writing M9.**
- 2026-11-10 (session 4, cont.): **M9 code written, also NOT compiled** (Flutter still downloading on the user's side). `mobile/` is ~12,000 lines of Dart (lib + tests) that have never been analysed or run. **Next step: first compile of M7+M8+M9 (checklist below). Strongly preferred before M10.**
- 2026-11-10 (session 4, cont.): **M10a (driver registration) written, NOT compiled.** M10 was split into three parts because the session's usage was nearly used up; only M10a is done. **Next step: first compile of everything in `mobile/` (checklist below), then M10b.**
- 2026-11-10 (session 4, evening): **M10b (driver mode: trips and requests) written, NOT compiled** — the user asked to keep writing and deal with compile problems once the SDK is installed. `mobile/` is ~14,500 lines of Dart never analysed or run. **Next step: M10c, or the first compile if Flutter is ready (checklist below).**
- 2026-11-10 (session 4, evening): **M10c written, NOT compiled. M10 is complete on paper**, which makes every screen in the spec's passenger (P), sender (S) and driver (D) lists written at least once. `mobile/` is ~17,000 lines of Dart never analysed or run. **Next step: the first compile (checklist below) — M11 (end-to-end testing) can't start without it.**

### Where the code lives (important)
**GitHub: `davidpeterson2242002-commits/musafir-maak` (private), branch `main`.** This is the source of truth for code; each chat's workspace is wiped.
- Start of every session: attach the repo (add_repo, push access) and clone it to `/home/claude/musafir-maak`; set up the workspace (below); then `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed` and run `php artisan test`. (`composer install` took ~12 minutes in session 4: zip downloads from api.github.com failed and Composer fell back to git clones — let it run, don't restart it.)
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
| M2 | Driver registration, vehicles, private document uploads (signed URLs), verification queue (drivers / vehicles / passenger IDs) | **Done 2026-11-09** |
| M3 | Trips + points, search (filters, sort, nearby dates, closure msg), bookings, 12h-or-departure expiry + hourly reminders (scheduler), cancellations + late-cancel penalties, no-show + 24h auto-reversal, route alerts, search logging | **Done 2026-11-09** |
| M4 | Fee engine (percentage / per seat / per trip, per vehicle type, min/max, snapshot at acceptance), free bookings, ledger, payment methods, settlement requests, optional wallet, weekly cycle + balance limit + grace, auto-freeze/unfreeze | **Done 2026-11-09** |
| M5 | Parcels (sizes, payer, prohibited items ack, pickup photo, 4-digit code), chat with number masking + flagging, ratings + low-rating flag, reports + enforcement, blocks | **Done 2026-11-09** |
| M6 | Real providers (FCM, WhatsApp Cloud API, mail), per-event channel matrix in admin, notification log, basic broadcast | **Done 2026-11-10** |
| M7 | Flutter foundation: RTL Arabic theme, API client, auth/OTP, profile, offline cache | **Written 2026-11-10, awaiting first compile** |
| M8 | Passenger flows: home, search, trip details, ID step, booking, status, my bookings, rating, alerts | **Written 2026-11-10, awaiting first compile** |
| M9 | Parcels, chat, public profiles, reports | **Written 2026-11-10, awaiting first compile** |
| M10a | Driver registration: intro, checklist (identity, licence, vehicle + photos), submit, under review / rejected / approved, vehicles | **Written 2026-11-10, awaiting first compile** |
| M10b | Driver mode: passenger/driver switch, driver home (status banner, balance card, pending requests), post a trip (D7), my trips (D8), trip management (D9: passengers, depart, complete, cancel, no-show), booking request detail with accept / reject (D10) | **Written 2026-11-10, awaiting first compile** |
| M10c | Driver parcels (D11–D12: accept / reject, pickup photo, refuse, delivery code), balance + ledger + submit payment (D13–D14), rate passengers (D15) | **Written 2026-11-10, awaiting first compile** |
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

## M2 — what was built
**API** (all under `/api/v1`, token auth): `GET me/identity`, `POST me/identity/documents`, `POST me/identity/submit`; `GET/POST driver`, `POST driver/documents`, `POST driver/submit`; `POST driver/vehicles`, `PUT/DELETE driver/vehicles/{id}`, `POST driver/vehicles/{id}/documents`, `POST driver/vehicles/{id}/submit`. `GET me` now returns `driver_status` and `is_driver`. Full table in README.md.

**Flow**
- Passenger ID: upload selfie + (passport, or national ID front + back) → submit with `document_type` → user `id_verification_status=pending` + a `verification_reviews` row. Documents locked while pending/verified. `User::idAllowsBooking()` implements spec 5.12 (verified always; pending only if setting allows) — M3 booking must call it.
- Driver: `POST driver` (needs completed profile) creates a `draft` profile. Identity docs use the shared identity endpoint; licence via `driver/documents`; vehicles with 5 photos each. `POST driver/submit` validates everything and returns all missing items at once. Status → `pending_review`, vehicles → `pending`, user ID → `pending` if not yet verified. One review covers identity + licence + vehicles.
- Approve driver → driver `active`, `free_bookings_remaining` = setting `drivers.free_bookings` (first approval only), pending vehicles approved, user ID verified, any separate pending passenger-ID review settled.
- Reject / request info → driver `rejected` (editable), pending vehicles `rejected`; user ID rejected too unless the reviewer ticks "identity OK". The user sees reasons + note, fixes and resubmits (new review row; history kept).
- Vehicles added after approval: own review via `driver/vehicles/{id}/submit`; driver stays active. Max 5 vehicles. Plate unique among non-rejected vehicles, compared normalized (spaces/dashes ignored, Arabic digits → ASCII). Seat caps per vehicle type (`VehicleType::maxSeats`).
- `DriverProfile::canOperate()` = active + ≥1 approved vehicle — M3 trip posting must call it.

**Key code**
- `app/Services/VerificationService.php` (submissions, approve, reject/info, claim, row lock against double decisions, audit log `verification.*`).
- `app/Services/DocumentStore.php` (one current file per owner+kind; replacing deletes the old file). `app/Models/Document::temporaryUrl()` (signed; unsigned/tampered → 403, verified).
- `app/Services/Notifier.php` + `app/Enums/NotificationEvent.php` (push to all device tokens, prunes invalid ones; WhatsApp for important events per spec 10; runs after commit; never throws). M6 makes channels configurable.
- Morph map enforced (`user, admin, driver_profile, vehicle, verification_review, setting, state, city, route`).
- Filament: `Verifications` (queue tabs: pending / passenger IDs / drivers / vehicles / history; priority then oldest first; selfie beside ID; vehicle photos; previous submissions; approve / reject with reasons from setting `verification.rejection_reasons` / request info; opening claims the item for 15 min, others get a warning; "next in queue"), `Drivers` (list/view), dashboard `OverviewStats` widget. Phone numbers in admin use `Phone::displayLtr()` so "+249" stays left in RTL.
- `database/seeders/DemoSeeder.php` — local demo: 2 pending passenger IDs + 2 pending driver applications with placeholder images.
- New settings: `verification.rejection_reasons` (list), `verification.max_upload_kb` (8192). Run `php artisan settings:sync` on an existing DB.

**Tests (95 total, 37 new):** identity upload/replace/kind+type validation, submit per document type, locking, approve (WhatsApp + audit), reject → resubmit, booking allowance setting, double-decision guard; driver full API flow, missing-items errors, verified user skips identity, seat caps, plate duplicates (normalized) and reuse after rejection, locking while pending, ownership 404s, approval effects, settling separate ID review, reject + resubmit, identity-OK option, free bookings only first time, new vehicle for active driver, draft can't submit vehicle alone, notification failure doesn't block, push token pruning; admin queue access per role, tab filtering + priority order, claim, approve/reject/info via Livewire, hidden actions after decision, dashboard widget.

## M3 — what was built
**API**: passenger `GET trips/search`, `GET trips/{id}`, `POST trips/{id}/bookings`, `GET bookings`, `GET bookings/{id}`, `POST bookings/{id}/cancel`, `POST bookings/{id}/dispute-no-show`, `GET/POST/DELETE route-alerts`; driver `GET/POST driver/trips`, `GET/PUT driver/trips/{id}`, `POST driver/trips/{id}/cancel|depart|complete`, `GET driver/requests`, `POST driver/bookings/{id}/accept|reject|no-show`. Full table in README.md.

**Conventions introduced**
- Times: stored UTC; API returns Sudan time with offset via `local_iso()`; `departs_at` input accepts "Y-m-d H:i" in Sudan time (`config('app.display_timezone')`, helpers `local_time()` / `local_iso()` in `app/helpers.php`). Search `date` is a Sudan-local day (01:30 local belongs to that day).
- Business-rule failures throw `App\Exceptions\BusinessRuleException` → HTTP 422 `{message, code, ...context}`. Codes used so far: id_verification_required, booking_restricted, profile_incomplete, account_suspended, own_trip, trip_unavailable, not_enough_seats, women_only, invalid_pickup_point, no_extra_luggage, already_booked, not_pending, expired, driver_not_active, not_cancellable, trip_departed, too_early, not_disputable, already_disputed, dispute_window_closed, no_open_dispute, vehicle_not_approved, too_many_seats, route_closed, route_unavailable, city_unavailable, same_city, price_above_max, departure_too_soon, departure_too_far, trip_not_scheduled, trip_has_bookings, not_departed, too_many_alerts, has_active_bookings, has_active_trips.
- Domain events for M4 (dispatched after commit): `BookingAccepted`, `BookingCancelled` (passenger cancels an accepted booking), `NoShowConfirmed` (auto after 24h or admin), `TripCancelled` (with booking ids). **M4 must add listeners**: record fee on accept (snapshot), reverse on cancel / no-show confirmed / trip cancelled. `bookings.fee_amount` / `fee_free` columns already exist.

**Rules implemented**
- Posting (`TripService::post`): driver must be `active`; vehicle approved and own; seats ≤ vehicle seats; price ≤ route max; departure between `trips.min_minutes_ahead` (30 min) and `trips.max_days_ahead` (60 days); ≥1 pickup and drop-off point; parcel prices + max required when accepting parcels. Route resolved by city pair; auto-created if `routes.auto_create` and both cities available; closed route → `route_closed` with its message. Route-alert subscribers notified after commit.
- Editing: full edit before any active booking; afterwards only `notes` and increasing `seats_total`. Pickup points referenced by bookings are kept.
- Cancel trip: all pending/accepted bookings → `cancelled_by_driver`, passengers get push + WhatsApp. Depart: from `trips.depart_early_minutes` (60) before departure; unanswered requests expire. Complete: accepted → completed. Departed trips auto-complete after `trips.auto_complete_hours` (48).
- Search (`SearchService`): bookable = scheduled, not hidden, future, driver `active` (frozen drivers' trips hidden), seats ≥ requested, not own. Filters: time_of_day (night 0–5, morning 5–12, afternoon 12–17, evening 17–24, Sudan time), max_price, vehicle_types, women_only, min_rating; sort earliest/cheapest/rating. Empty day → `nearby_dates` (± `search.nearby_days`). Closed route → `closed` + message. Every search → `search_logs`.
- Booking request (`BookingService::request`): own-trip check, then profile complete, not suspended, not restricted, `idAllowsBooking()`; a pending ID's review gets priority 10. Women-only: female or `includes_family`. Pending requests don't hold seats; accept locks the trip row and checks seats. `expires_at` = min(now + `booking.response_timeout_hours`, departure). One active booking per passenger per trip.
- Accept: seats decremented; passenger WhatsApp includes driver name, phone, car and plate; booking API then shows `contact` + `share_text`. Frozen/inactive driver can't accept but can reject. Reject/expired/cancelled-by-driver → `alternatives` (same route, same Sudan day).
- Timers (`php artisan musafir:tick`, scheduled every minute, idempotent; `App\Services\TripTimers`): expire overdue requests (+ push to passenger), remind drivers every `booking.reminder_interval_minutes` (first after one interval), trip reminders evening before (after `booking.reminder_evening_hour`, 20:00) and `booking.reminder_soon_hours` (2) before, to driver and accepted passengers, once each; confirm undisputed no-shows; auto-complete; deactivate past dated alerts.
- Passenger cancel: any active booking before departure. Late = accepted and under `cancellation.late_window_hours` (3h) before departure. Late count in `cancellation.passenger_window_days` (30): at limit−1 a warning (push + WhatsApp), at limit (3) `users.booking_restricted_until` = now + `cancellation.passenger_restriction_days` (7). Cancelling a pending request is never penalised.
- No-show: driver marks accepted booking after departure time; passenger can dispute within `booking.no_show_dispute_hours` (24); undisputed → confirmed by timer; disputed → admin confirms (fee reversal) or overturns (booking completed).
- Driver cancellation count (last `cancellation.driver_window_days`) shown on admin driver page, red at `cancellation.driver_limit`. No automatic suspension yet (spec says "can lead to"); enforcement tools come in M5.
- Account deletion blocked with active bookings or scheduled/departed trips (balance check in M4).

**Admin**: Trips (tabs upcoming / on the road / all; filters status, route, date range, hidden; view with bookings; cancel with reason → passengers notified + audit; hide/unhide from search), Bookings (tabs all / pending / open no-show disputes with nav badge; view with timeline; confirm or overturn no-show), Users (shows booking restriction; "lift booking restriction" action), Drivers (recent cancellations, completed trips), dashboard (trips today, bookings this week, zero-result searches table for driver recruitment).

**New settings**: `booking.reminder_evening_hour` (20), `booking.reminder_soon_hours` (2), `trips.min_minutes_ahead` (30), `trips.max_days_ahead` (60), `trips.depart_early_minutes` (60), `trips.auto_complete_hours` (48), `search.nearby_days` (3), `route_alerts.max_per_user` (10). Run `php artisan settings:sync` on an existing DB.

**Tests (147 total, 52 new):** trip posting rules, auto-route + closed route, inactive driver, route-alert notifications, full vs limited edit, cancel trip (bookings, WhatsApp, event), depart/complete, auto-complete, ownership; search local-date boundaries, unbookable trips hidden, own trips excluded, every filter + sorts, nearby dates, closure, logging, parcel mode, details hide plate, validation; booking request, 12h-or-departure deadline, ID rules + priority, all booking rules, auto pickup point, women-only, accept (seats, contacts, event, WhatsApp with plate), over-accept/double accept, frozen driver, alternatives, ownership, expiry after 12h (idempotent), hourly reminders, trip reminders, cancel pending/accepted, warn-then-restrict, no cancel after departure, no-show window + auto-confirm, admin resolution, tabs, route alerts CRUD/limit/expiry, deletion guard; admin pages per role, admin cancel/hide, dispute tab + overturn, lift restriction, zero-result widget.

**Testing gotcha:** lazy loading is disabled outside production but only flagged when several models are loaded together; tests for list/detail pages must create 2+ related rows (the trip view bug was caught by a screenshot, not the 1-booking test). Filament adds a primary-key tie-break sort — use `->defaultKeySort(false)` on grouped queries.

## M4 — what was built
**API** (driver, token auth): `GET driver/balance`, `GET driver/ledger`, `GET payment-methods`, `GET/POST driver/settlements` (multipart receipt). `GET driver/requests` / trip bookings now include `fee` (preview while pending: `{amount, free, free_bookings_remaining}`; after acceptance: `{amount, free, reversed}`). `GET driver` adds `settlement_due_at`, `balance_limit_reached`. Full table in README.md.

**Money model** (`app/Services/LedgerService.php`, the only writer):
- `driver_profiles.balance_owed` (postpaid, owed to us) and `wallet_balance` (prepaid credit). Append-only `ledger_entries` (model throws on update/delete) with signed `amount` per bucket and running `balance_after` / `wallet_after`. Every method locks the driver row.
- **Charge at acceptance** (`chargeBooking`, called inside `BookingService::accept`'s transaction; an exception rolls back the acceptance): checks balance limit and mandatory wallet → free booking if any left (`fee_free=true`, count decremented) → otherwise `FeeCalculator` amount snapshotted on the booking (`fee_amount`, `fee_model`) → taken from wallet first, remainder to balance. Balance going 0 → >0 starts a cycle: `settlement_due_at = now + settlement.cycle_days`.
- **Fee models** (`app/Services/FeeCalculator.php`): percentage = seats × price × `fees.percent.{type}`%; fixed_per_seat = seats × `fees.fixed.{type}`; fixed_per_trip = `fees.fixed.{type}` once per trip (first accepted booking; if that booking is reversed the fee moves to another accepted booking on the trip). `fees.min_per_booking` / `fees.max_per_booking` clamp all modes. Extra-luggage money is not commissioned. Whole SDG, PHP `round` (half up). `parcelFee(size, price)` ready for M5 (`fees.parcel_model`: percentage / fixed_per_size).
- **Reversals** (`reverseBooking`, idempotent via `bookings.fee_reversed_at`), wired as listeners in `AppServiceProvider`: `BookingCancelled` (passenger cancels an accepted booking), `NoShowConfirmed`, `TripCancelled` (each booking). Refunds exactly what each bucket took; if the fee was already paid, the refund becomes wallet credit; free bookings give the free booking back.
- **Payments**: driver submits `settlement_requests` (method, purpose `settlement` | `wallet_topup`, amount, reference, receipt photo on the private disk). Rules: nothing to settle → `nothing_to_settle`; one pending request per purpose (`payment_pending`); a settlement reference can't be reused (`duplicate_reference`); reference required unless the method says otherwise (cash at agent). Finance confirms → payment entry; overpayment goes to wallet; top-up → wallet. Reject needs a reason.
- **Cycle** (`app/Services/SettlementCycle.php`, in `musafir:tick`): reminder once per cycle `settlement.reminder_days_before` days before due (push + email); freeze when active, owing and `now > due + settlement.grace_hours` (push + WhatsApp). Fully paid (balance 0) → due date cleared, frozen → active (push). Partial payment keeps the due date and the freeze. Weekly email statement: `musafir:weekly-statements`, Saturday 09:00 Sudan time.
- **Frozen**: can't accept (`driver_frozen`) or post trips; trips hidden from search (M3); accepted bookings stay valid with contacts visible. **Balance limit** (`settlement.balance_limit`, 50,000 placeholder): at/over the limit, accepting is refused (`balance_limit_reached`) but the driver isn't frozen. **Mandatory wallet** (`wallet.mode=mandatory`): accepting needs wallet ≥ fee (`wallet_insufficient`), free bookings excepted.
- Admin `adjust(bucket, signed amount, reason)` (can't go negative; owing from zero starts a cycle) and `grantFreeBookings`, both audited. Account deletion blocked while owing (`has_balance_owed`).
- Email channel: `NotificationEvent::sendsEmail()` → `App\Mail\NotificationMail` (RTL HTML) when the user has an email: settlement due, payment confirmed/rejected, weekly statement (email only).

**Admin** (group المالية, role Finance + super admin): تأكيد المدفوعات (pending/confirmed/rejected tabs, nav badge, receipt image, driver balance beside it, confirm with amount in the dialog, reject with reason), دفتر الحسابات (all entries, filters type/bucket/driver/date, CSV export of the filtered rows with UTF-8 BOM, export audited), طرق الدفع (CRUD, enable toggle, drag to reorder, agent locations). Drivers list: owed, wallet, due date (red + days overdue), filters "owes" / "overdue"; driver page: balance, wallet, due, frozen since, actions كشف الحساب / تسوية يدوية / منح حجوزات مجانية (finance only). Dashboard `FinanceStats` (finance only): fees and reversals (7 days), confirmed payments, total owed + overdue count, frozen count, pending payments. Admin numbers use Western digits (`defaultNumberLocale('en')` for tables and schemas).
- Seeded payment methods (`PaymentMethodSeeder`): بنكك، فوري، أوكاش، كاش عند وكيل — account details are placeholders to fill in before launch.

**Tests (190 total, 43 new):** fee calculator (all models, per type, rounding, min/max, parcels); commission (3 free bookings, balance + cycle start, snapshot, wallet first, reversals on cancel / trip cancel / no-show, wallet refund, paid-fee refund to wallet, idempotency + free booking restored, per-trip once + move, balance limit with rollback, mandatory wallet, fee preview, deletion guard); settlements (balance screen, submit with receipt, validation, duplicate reference, confirm + email + audit, overpayment and partial, top-up, reject, reminder → freeze timing, frozen blocked until fully paid, accepted bookings valid when frozen, adjust + free bookings, weekly statement, append-only ledger); admin finance (role access, confirm/reject, adjust + grant, support can't adjust, payment methods, CSV export, widget).

## M5 — what was built
**API**: parcels (sender) `POST trips/{id}/parcels` (multipart), `GET parcels`, `GET parcels/{id}`, `POST parcels/{id}/cancel|dispute`; parcels (driver) `GET driver/parcels`, `POST driver/parcels/{id}/accept|reject|pickup|refuse|deliver`; chat `GET conversations`, `GET conversations/unread-count`, `GET/POST conversations/{id}/messages`; `POST ratings`, `GET ratings/pending`; `GET users/{id}` (public profile + reviews), `POST/DELETE users/{id}/block`, `GET me/blocks`; `POST reports`. Bookings and parcels expose `conversation_id`; driver trip view lists `parcels` and `parcel_slots_left`; trip details add `parcel_slots_left`; `GET config` adds `rating_tags` and `report_reasons`. Full table in README.md.

**Parcels** (`app/Services/ParcelService.php`)
- Request: trip must accept parcels and have a free slot (`max_parcels` minus accepted/picked up/delivered/disputed); same checks as bookings (own trip, `assertCanBook` incl. ID rules, blocks); price = trip's price for the size; prohibited-items checkbox required (`accept_prohibited`); receiver phone normalized; parcel photo on private disk; 12h-or-departure deadline; conversation opened; driver push + WhatsApp.
- Accept: slot re-checked under lock; 4-digit `delivery_code` generated (stored with Laravel `encrypted` cast because the sender must see it); commission via `LedgerService::chargeParcel` (`FeeCalculator::parcelFee`; free bookings count covers parcels; balance limit and mandatory wallet apply). After commit the receiver gets a WhatsApp message (spec S4: sender, driver name/phone, car + plate, date, who pays and how much, the code). WhatsApp failure is logged; the sender still has the code and a `share_text` in the app.
- Pickup: driver uploads a photo → `picked_up`. Refuse at pickup (contents don't match) → `refused`, fee reversed. Deliver: driver enters the code; wrong code counts attempts (`parcels.code_max_attempts`, 5) then locks (`code_locked`, support confirms manually).
- Sender cancels before pickup (fee reversed if accepted). Trip cancellation cancels open parcels (incl. picked up — sender told to collect) and reverses fees. Driver departure expires pending parcel requests. Timers expire overdue requests and remind drivers.
- Dispute (sender): while picked up, or within `parcels.dispute_days` (3) after delivery → `disputed`; admin resolves "delivered" (fee stays) or "cancelled" (fee reversed), audited.

**Chat** (`app/Services/ChatService.php`): one `conversations` row per booking/parcel (customer + driver), opened at request. Before acceptance, phone-like numbers (valid Sudanese numbers, international numbers, or 9+ digits; Arabic-Indic digits and separators handled) and WhatsApp/Telegram links are replaced with "[رقم مخفي — يظهر بعد قبول الحجز]"; the original is kept encrypted in `messages.original_body` and the message is flagged `number_before_acceptance`. Messages containing words from `chat.flag_keywords` are flagged `keyword` but delivered unchanged. Dates, times and prices are not masked. Sending is allowed while the booking/parcel is active and for `chat.days_after_trip` (3) days after the trip; closed after rejection/expiry/cancellation. Blocked pairs can't send. Unread counts via per-side `*_last_read_at`; reading messages marks read; recipient gets a push. Polling (no websockets).

**Ratings** (`app/Services/RatingService.php`): after a completed booking or delivered parcel, both sides rate (1–5, comment, tags from `App\Support\RatingTags`); no-show → only the driver can rate; window `ratings.window_days` (14); one rating per rater per subject. Averages: driver ratings → `driver_profiles.rating_avg/count`; passenger ratings → `users.rating_avg/count`; hidden ratings excluded. Low-rating flag `driver_profiles.low_rating_flagged_at` when count ≥ `ratings.review_min_trips` and avg < `ratings.review_threshold` (cleared on recovery). Trip completion pushes "rate your trip". `GET ratings/pending` lists what to rate.

**Reports & enforcement**: `POST reports` with reason (`App\Enums\ReportReason`), optional subject (booking/parcel/trip/conversation) that must involve both users; safety-critical reasons (harassment, wrong person/vehicle, unsafe driving) get priority 1; daily cap `reports.max_per_day`. `App\Services\EnforcementService`: warn, suspend (until), ban, reactivate → `enforcement_actions` row (optionally linked to a report), audit log `user.warned|suspended|banned|reactivated`, user notified (push + WhatsApp). Ban also revokes tokens, cancels the user's scheduled trips (passengers notified, fees reversed), their open bookings (no late penalty) and open parcels. Suspended users can't book, post trips or accept. Admin user actions now go through this service.

**Blocks**: `user_blocks`; either direction hides the driver's trips from search, refuses bookings/parcels (`blocked`), marks trip details not bookable, and stops chat.

**Admin**: الارساليات (tabs all / on the road / disputes with badge; photos from sender and at pickup; resolve dispute), البلاغات (safety first then oldest; opening sets "in review" and the handler; reporter + reported with history; chat log of the related booking/parcel showing masked text and the original; actions warn / suspend 1–30 days / ban / reverse fee; close with note), محادثات معلّمة (only flagged or reported chats are listable/viewable; every view is audited as `chat.viewed`), التقييمات (filters, hide/show → averages recalculated). Users page: history (reports by/against, completed bookings, parcels, past enforcement), new "warn" action. Drivers: rating shown with ⚠ when flagged, filter "low rating". Trip page lists parcels. Dashboard `TrustAlerts` (support): open reports (+ safety count), parcel disputes, no-show disputes, low-rated drivers, flagged chats (7 days).

**New settings**: `parcels.code_max_attempts` (5), `parcels.dispute_days` (3), `chat.flag_keywords` (list), `chat.days_after_trip` (3), `ratings.window_days` (14), `reports.max_per_day` (10). Run `php artisan settings:sync` on an existing DB. `APP_FAKER_LOCALE=ar_SA` so demo/factory names are Arabic.

**Tests (246 total, 56 new):** parcels (request + validation + rules, accept → code, encryption, receiver WhatsApp content, fee, free bookings, slots + search, WhatsApp failure, pickup + deliver, wrong code + lock, refuse, sender cancel, trip cancel, dispute + resolution, expiry, driver trip view); chat masking unit cases (masked and untouched); chat (opened with booking, mask + flag + original, visible after acceptance, keyword flag, unread/push/read, participants only, blocks, closed after rejection); ratings (both sides + tags, rules, no-show, low-rating flag + hide, pending + prompt, public profile); reports/blocks/enforcement (report from booking + priority, rules, blocking both ways, suspended can't book/accept, ban cancels driver trips, ban cancels passenger bookings without penalty); admin (pages per role, chat log shows original, finance blocked, report review + warn, suspend + resolve, flagged chat audited / unflagged 404, parcel dispute, hide rating, alerts widget).

## M6 — what was built
No API changes for the app (device tokens were already `POST/DELETE devices`). Everything is backend + admin.

**Delivery pipeline**
- `Notifier::send()` (same signature for callers, plus optional forced channels / broadcast id) → after commit, one queued `App\Jobs\DeliverNotification` per enabled channel (`tries=3`, backoff 30s / 180s) → gateway → row in `notification_logs`. Still never throws into business code. Email is not queued for users without an address; push without a device is logged `skipped`.
- Channels per event come from `App\Support\ChannelMatrix` (table `notification_channels`, cached, flushed on save). A missing row = the event's defaults (`NotificationEvent::defaultChannels()`, spec section 10). `settings:sync` / `notifications:sync` add rows for new events without touching admin changes.
- `notification_logs`: user or phone, event, channel, status (`sent` / `failed` / `skipped`), title, provider error, broadcast id. The message body is not stored (it can contain delivery codes); OTP rows have no title either. Pruned daily (`notifications:prune`, setting `notifications.log_retention_days`, 60).
- OTP stays synchronous (the app needs the 503 immediately) but is logged. The parcel receiver's WhatsApp message is now a queued job (`SendParcelCodeToReceiver`, retried, skipped if the parcel was cancelled meanwhile) and is logged with the receiver's phone.

**Real providers** (still `log` by default; `fake` in tests)
- `WHATSAPP_DRIVER=meta` → `App\Gateways\MetaWhatsAppGateway` (WhatsApp Cloud API, `graph.facebook.com/{version}/{phone_number_id}/messages`). Every message is a template. Map in `config/whatsapp.php`: `otp_code` → `musafir_otp` (authentication template, code also sent to the copy-code button), `parcel_delivery_code` → `musafir_parcel_code` (sender, details, code), `broadcast` → `musafir_announcement`, everything else → `musafir_notice` (title, body). Variables are cleaned (no line breaks, trimmed to 600 chars, never empty). API errors become `GatewayException` with Meta's code and detail. Wording to submit to Meta: `docs/WHATSAPP_TEMPLATES.md`.
- `PUSH_DRIVER=fcm` → `App\Gateways\FcmPushGateway` (FCM HTTP v1). Service-account key from `FCM_CREDENTIALS` (path) or `FCM_CREDENTIALS_BASE64`; RS256 JWT signed with openssl (no Google SDK), access token cached; tokens sent concurrently (pool of 20); `UNREGISTERED` / malformed tokens are returned for deletion; throws only when nothing could be delivered (so the job retries). Data payload values are strings (`event`, ids) for deep links in the app.
- Email: Laravel mailer (`MAIL_MAILER=smtp` or Resend/Postmark/SES) — config only.
- `php artisan notifications:test <phone> [--channel=push|whatsapp|email]` sends a test through the configured providers and prints sent / skipped / the provider error.

**Broadcast** (`App\Services\BroadcastService`, `App\Jobs\SendBroadcast`): segments all users / users in a city / all drivers (active + frozen) / drivers in a city / drivers who owe a balance / passengers who searched a route in the last `notifications.broadcast_search_days` (30). Banned and deleted users and unfinished profiles are excluded. The admin picks the channels per broadcast (push by default). Each recipient/channel is its own delivery job, logged with the broadcast id; the fan-out job never retries (no double sends). Audited as `broadcast.sent`.

**Admin** (new group الإشعارات)
- قنوات الإشعارات — the matrix: one row per event with push / WhatsApp / email toggles (saved instantly, audited), "reset to defaults" action. Super admin only.
- الإشعارات الجماعية — list + "send" modal (segment, city or route, live recipient count, channels with cost hints, title, text), view page with sent / skipped / failed counts. Super admin only.
- سجل الإشعارات — read-only log, tabs all / failed / WhatsApp, filters status / channel / event, search by name or phone, nav badge = failures in the last 24h. Support + super admin.
- Settings: new tab الإشعارات (log retention, broadcast search window).

**Production checklist added by M6**: run a queue worker (`php artisan queue:work --tries=3 --max-time=3600` under Supervisor) next to the cron entry; `QUEUE_CONNECTION=database` is the default.

**Tests (280 total, 34 new):** matrix defaults = spec table, fallback + sync keeps admin changes, channels applied immediately, per-channel logs, skipped push / no email job, provider failure logged and swallowed, dead-token pruning, queued job per channel, deleted user, OTP logged without code, parcel receiver template values + log, prune, test command; Meta gateway (default / OTP / parcel templates, cleaning, error mapping, config), FCM (auth JWT + token cache, payload, unregistered tokens, total failure, auth failure, credentials); admin (role access, toggle + audit, reset, log tabs + badge, audience segments, send broadcast end-to-end, validation, no double send).

**Not done on purpose** (candidates for later): WhatsApp delivery-status webhooks (we log "accepted by Meta", not "delivered/read"); per-user notification preferences (spec P11 "Settings: notifications") — to decide in M7 with the app's settings screen; an in-app notification inbox (not in the spec).

## M7 — what was built (uncompiled)
`mobile/` — Flutter app, Dart only (no `android/` folder yet; `flutter create .` generates it — steps in `mobile/README.md`). ~4,800 lines incl. tests.

**Dependencies** (kept few and on APIs that are stable): dio ^5.7, flutter_riverpod ^2.6 (Notifier / StateProvider — stay on 2.x), go_router ^14.6, flutter_secure_storage ^9.2, shared_preferences ^2.3, image_picker ^1.1, url_launcher ^6.3, flutter_localizations. No codegen, no Firebase yet.

**Foundation**
- `core/api/api_client.dart`: Dio wrapper; adds the Bearer token, unwraps Laravel's `{data: …}` envelope, converts every failure to `ApiException` (`kind`: network / timeout / unauthorized / forbidden / notFound / validation / rateLimited / server; `code` for business rules; `fieldErrors` = first message per field; message shown as-is because the backend answers in Arabic). 401 or `account_banned` on an authenticated call signs the user out. `offlineProvider` flips on connection failures → `OfflineBanner`.
- `core/storage`: `TokenStore` (Keystore via flutter_secure_storage, memory impl for tests), `CacheStore` (JSON + timestamp in shared_preferences; keys under `user.` are wiped on logout).
- `features/auth/session_controller.dart`: restore at startup (cached profile shown immediately → works offline, refreshed in background), sign in, sign out, account deleted, forced sign-out. `core/router/app_router.dart`: go_router + pure `redirectFor()` (splash → welcome/login → setup → home; `/page/*` always readable).
- Cached data: `GET config` (network first, cache fallback, built-in defaults last), cities and popular routes (cache fresh for 12h → no request on most app starts), `me`.
- `core/strings.dart`: all Arabic text in one class (`S`), Sudanese colloquial wording. `core/utils`: phone normalisation mirroring the backend, formatting with Western digits.
- `features/push/push_service.dart`: interface + no-op; Firebase implementation waits for the Firebase project (`POST/DELETE devices` already exist).

**Screens**: splash; onboarding (3 pages, P1); phone → 6-digit code with resend countdown, auto-submit, debug-code autofill (P2); profile setup (photo, name, gender, city, email, terms links); home shell with 4 tabs; home search form (trip/parcel toggle, from/to with swap, date with today/tomorrow, seats, popular-route chips that fill the form — the search button is wired in M8); bookings and messages tabs as empty states; profile tab (header, ID status card, become-driver card, menu, WhatsApp support if `content.support_whatsapp` is set, logout, delete account); edit profile (photo, name, city, email, WhatsApp number); terms / privacy / prohibited items pages from config; searchable city picker.

**Design**: green `#0B6E5B` (highway signboard) + amber `#F2B01E` (road centre line) on a pale background; fonts bundled in `assets/fonts`: Lalezar (display: app name, screen titles, signs) + Tajawal 400/500/700 (body), ≈470 KB total, OFL licences included. Signature element: the green road sign (onboarding headings, route chips) and the dashed amber route line. Icons are Material icons; illustrations are drawn in code (no image assets). Previews: `docs/design/m7-login-flow.png`, `docs/design/m7-app-screens.png` — these are HTML mock-ups built from the same colours, fonts and texts, not screenshots of the running app.

**Tests written (not run)**: `test/phone_test.dart`, `api_exception_test.dart`, `models_test.dart`, `cache_store_test.dart`, `session_test.dart` (session restore/sign-in/out with fakes + routing rules).

## M8 — what was built (uncompiled)
No backend changes; everything uses the M3–M5 API as is.

**Screens / routes** (all under the signed-in area in `app_router.dart`)
- `/search` — results (P4): trip cards (time, vehicle, driver + rating + verified mark, price, seats left, women-only / parcels tags), sort chips (earliest / cheapest / rating), filters sheet (time of day, vehicle type, min driver rating, women-only), closed-route message, empty day → nearby days with trips (tap = search that day) + "alert me" (creates a route alert for that route and date). Parcel mode lists parcel-accepting trips with the lowest parcel price; sending a parcel itself is M9.
- `/trips/:id` — trip page (P5): summary, driver, vehicle + plate-free photos, pickup / drop-off points, luggage allowance, notes, women-only notice; bottom bar = price + "احجز مقعد" (or "افتح حجزي" when `my_booking_id` is set, disabled when not bookable).
- `/trips/:id/book` — booking request (P6): seats (≤ available), pickup point, extra bags (only if the driver takes them), "with my family" on women-only trips for non-female users, message, total. If the user's ID is none/rejected the button reads "وثّق هويتك وأكمل الحجز" and opens the ID step first; a server `id_verification_required` also routes there, then the request is retried.
- `/identity` — ID verification (5.12): national ID (selfie + front + back) or passport (selfie + data page); camera only; each photo uploads as it is taken (1600px, quality 80); rejection reasons shown; submit → pending. Pops `true` when booking is now allowed. Also opened from the profile card.
- `/bookings/:id` — booking page (P7): status banner with countdown while pending (refreshes every 30s), driver phone / plate / car + call / WhatsApp once accepted, booking facts, share-with-family sheet (WhatsApp or copy), cancel (with late-cancellation warning and the server's warning text), no-show dispute, alternatives when rejected / expired / cancelled by driver, "rate" when a rating is pending. Chat button shows "coming next update" (M9).
- Bookings tab (P8): upcoming / past / cancelled. Home: "your next trip" card and a "rate your trip" prompt above the search form.
- `/rate` — rating (P9): stars, tags from `GET config` (`rating_tags`), comment. `/alerts` — route alerts (P12): list, delete, add (from, to, any date or a date). Profile menu item now opens it.

**Data layer**: `models/trip.dart` (Trip, PublicUser, TripPoint, TripDetails, SearchResult), `booking.dart`, `identity.dart`, `misc.dart` (RouteAlert, PendingRating); repositories under `features/{trips,bookings,identity,ratings,alerts}`. Bookings are cached per list tab and per booking after every successful load and served from cache when offline (spec 12) — an accepted booking opens without a connection, with a "saved copy" strip. `ApiTime.wall()` keeps trip times as Sudan clock time regardless of the phone's timezone; `ApiTime.instant()` is used for countdowns. Dio now sends list query params as `key[]=…` (Laravel arrays). `Fmt.count()` handles Arabic number agreement (1 / 2 / 3–10 / 11+).

**Tests written (not run)**: `test/trips_bookings_test.dart` (time parsing, trip / booking / identity / alert parsing from real response shapes, filter query, wording).

**Previews** (HTML mock-ups, not app screenshots): `docs/design/m8-search-and-booking.png`, `docs/design/m8-bookings-and-rating.png`.

**Known gaps / to revisit after compiling**: bookings list loads the first page only (20); no map for pickup pins yet (label only); chat, reports, public profile page and parcels are M9; the search form keeps the previous user's "from" city after logout until the app restarts.

## M9 — what was built (uncompiled)
No backend changes.

- **Parcels (sender side)**: `/trips/:id/parcel` request form (S2) — description, size tiles with the trip's price per size, photo (rear camera or gallery, resized), receiver name + phone, who pays, note, prohibited-items list from config + confirmation; same ID step as bookings. `/parcels/:id` status page (S3) — status banner with countdown, large delivery code with "receiver got it on WhatsApp" / "didn't — send it yourself", "send details to receiver" (opens the receiver's WhatsApp with the ready message, or copy), driver contact + plate after acceptance, call / chat, pickup photo, cancel before pickup, problem report (dispute). Refreshes every 30s while in progress; cached for offline. Parcels are a 4th tab in "حجوزاتي" (active first). Trip page: a parcel button beside "احجز مقعد" when the trip has free parcel slots; arriving from a parcel search makes "أرسل ارسالية مع الرحلة دي" the main action.
- **Chat**: Messages tab = conversation list (other person, trip summary, last message, unread count); unread badge on the tab. `/chats/:id` — bubbles, polling every 6s with `after_id`, send keeps the text on failure, note under a message whose number was hidden, closed state with the reason, report button, tap the header for the profile. Opened from the booking page ("محادثة") and the parcel page.
- **Public profile** `/users/:id` (P13): photo, verified mark, ratings as driver / as passenger, trips, member since, reviews with tags, report, block / unblock. Opened from the trip page's driver card and the chat header.
- **Reports**: one bottom sheet (reasons from `GET config` → `report_reasons`, optional details) used from the booking page, parcel page, chat and profile, with the right subject attached. Shows the server's confirmation text.
- Models: `models/parcel.dart`, `models/chat.dart` (Conversation, ChatMessage, ChatPage, Review, PublicProfile); repositories in `features/{parcels,chat,users}`; `AppConfig.reportReasons`.
- Tests written (not run): `test/parcels_chat_test.dart`. Previews: `docs/design/m9-parcels-and-chat.png`, `docs/design/m9-profile-and-report.png` (HTML mock-ups).
- Known gaps: receiver's web tracking link is Phase 2; chat loads the latest 50 messages only (no "load older"); conversation list first page (30); the blocked-users list (`GET me/blocks`) has no screen yet — unblock is on the profile page.

## M10a — what was built (uncompiled)
No backend changes. Uses `GET/POST driver`, `POST driver/documents`, `POST driver/submit`, `POST/PUT/DELETE driver/vehicles…`, `POST driver/vehicles/{id}/documents|submit`.

- `/driver/register` (`features/driver/driver_registration_screen.dart`): one resumable screen that shows, by state — **intro** (D1: benefits, what to prepare, how fees work; "ابدأ التسجيل" creates the draft), **checklist** (draft / rejected: 1 identity + selfie → the shared identity screen in "collect only" mode, 2 driving-licence photo, 3 vehicle; rejection reasons on top; driver-terms checkbox; "أرسل الطلب للمراجعة" sends one application), **under review** (D5), **approved** (account state + vehicles list + add vehicle; says trip posting comes in the next update).
- `/driver/vehicle` (`vehicle_form_screen.dart`, D3): type chips from config, make, model, colour, plate, passenger seats, year; saved first, then the 5 photos (front, back, side, interior, شهادة البحث) upload one by one from the camera. Approved drivers send a new vehicle for its own review; approved vehicles are read-only; delete for non-approved ones.
- `IdentityScreen(collectOnly: true)` (route extra `'driver'`): uploads the ID photos without submitting a separate passenger-ID review — the driver application covers identity.
- Profile: the driver card is now a live entry (start / continue / under review / needs changes / active) instead of "قريباً".
- `models/driver.dart` (DriverAccount, Vehicle), `features/driver/driver_repository.dart`, shared `core/widgets/photo_tile.dart`. Test written (not run): `test/driver_test.dart`. Preview: `docs/design/m10a-driver-registration.png` (HTML mock-up).
- Not done: licence renewal / document update for approved drivers (support handles it, per the M2 decision); the driver's own profile page with reviews (use the public profile).

## M10b — what was built (uncompiled)
No backend changes. Uses `GET/POST driver/trips`, `GET/PUT driver/trips/{id}`, `…/cancel|depart|complete`, `GET driver/requests`, `POST driver/bookings/{id}/accept|reject|no-show`.

- **Mode switch**: `driverModeProvider` (in `features/driver/driver_trip_repository.dart`) — on only for approved drivers (`user.is_driver`), remembered in the user cache. A راكب / سائق switch sits at the top of the home shell for drivers. In driver mode tab 1 = driver home, tab 2 = "رحلاتي"; messages and profile are shared.
- **Driver home** (D6, `driver_home_screen.dart`): frozen / balance-limit banner, balance card (owed, due date or free bookings left; "سدّد" says payments come next update), "انشر رحلة", pending requests across all trips, next 3 trips.
- **Post / edit a trip** (D7, `post_trip_screen.dart`, routes `/driver/trips/new` and `/driver/trips/:id/edit`): from / to, date + time, vehicle (approved ones; chips when more than one), seats (≤ the vehicle's), price, pickup and drop-off points (text labels, up to 10), bags per passenger, extra-bag price, parcels switch with 3 prices + max count, women-only, notes. Sends `departs_at` as Sudan clock time without an offset (the server parses it in its display timezone). Server errors (price above the route cap, too soon, frozen) are shown as they come. Editing is offered only while the trip has no active bookings (`can_edit_all`), and the route is locked.
- **My trips** (D8): upcoming / ongoing / past / cancelled, cards with a seats-filled bar and a "requests waiting" tag.
- **Trip management** (D9, `/driver/trips/:id`): summary, seats bar, hidden-by-admin and parcels notices, bookings (pending first) — each a `DriverBookingCard` (D10): passenger (tap → public profile), verified / "ID under review" tag, seats, total, bags, family, pickup point, message, commission preview or "free booking", expiry countdown, accept / reject / chat; once accepted: phone, call, WhatsApp, chat, "ما حضر" when the server allows it. Bottom button: "بدأت الرحلة" → "وصلنا — أكمل الرحلة" (with confirmation). Cancel asks for a reason and warns when passengers are booked.
- Models `models/driver_trip.dart` (DriverTrip, DriverBooking, TripDraft); `DriverAccount` gained `settlementDueAt`, `balanceLimitReached`, `isFrozen`; shared `askTextSheet()` in `core/widgets/common.dart`. Test written (not run): `test/driver_trip_test.dart`. Preview: `docs/design/m10b-driver-mode.png` (HTML mock-up).
- Known gaps: limited edit of a trip that already has bookings (`can_edit_limited`) isn't offered — the driver must cancel and repost or contact passengers; pickup points have no map pin; no reject reason field (server accepts none); estimated arrival time isn't asked; parcels on a trip show only a count until M10c.

## M10c — what was built (uncompiled)
No backend changes. Uses `GET driver/parcels`, `POST driver/parcels/{id}/accept|reject|pickup|refuse|deliver`, `GET driver/balance|ledger|settlements`, `POST driver/settlements`, `GET payment-methods`.

- **Driver parcels** (D11/D12, `driver_parcel_card.dart`): one card per parcel with the sender's photo, size + description, receiver and city, price, who pays; **pending** → commission preview, countdown, accept / reject; **accepted** → sender and receiver phones, "استلمت الارسالية — صوّرها" (camera photo uploaded as pickup evidence) or "ارفض الاستلام" with a reason; **picked up** → "سلّمت الارسالية — أدخل الرمز" (4-digit sheet, shows attempts left; wrong-code errors come from the server). Chat link. Pending parcel requests appear on the driver home; all of a trip's parcels appear on the trip page (`DriverTrip.parcels`).
- **Balance** (D13, `/driver/balance`, `balance_screens.dart`): owed amount, due date / overdue, freeze date, cycle length, free bookings left, wallet balance if any, payments in review, "سدّد المستحق", last 5 reported payments with status and rejection reason, statement (ledger: label, note, time, signed amount — positive = charge, as the backend stores it). Opened from the driver-home balance card and the approved-driver page.
- **Report a payment** (D14, `/driver/pay`): payment methods from the admin panel as chips; the chosen one shows account name, number (copy button), instructions and cash-agent locations; amount (prefilled with what's owed), reference number (required when the method says so), receipt image from the gallery; server rules ("a payment is already in review", "reference already used") shown as they come.
- **Rate passengers** (D15): a prompt on the driver home for pending ratings where the driver is the rater; the existing rating screen shows passenger tags and "قيّم <name>".
- Models `models/driver_money.dart`; repository `features/driver/driver_money_repository.dart`. Test written (not run): `test/driver_money_test.dart`. Preview: `docs/design/m10c-driver-parcels-and-payments.png` (HTML mock-up).
- Known gaps: wallet top-up (`purpose: wallet_topup`) has no screen — only settlement payments; ledger and payments show the first page only; wallet-bucket ledger lines use the same sign display as balance lines; a driver's full parcel history (`status=done`) has no list outside the trip page.

### M7 — first compile checklist
1. `cd mobile && flutter create --platforms=android --org com.musafirmaak --project-name musafir_maak . && flutter pub get` (delete `test/widget_test.dart` if created).
2. `flutter analyze` and `flutter test` — expect some errors on the first run (never compiled). Paste the output into the chat, or connect the project folder so Claude can run them.
3. Manifest: INTERNET permission + cleartext for local http (README).
4. Run against the local backend with `--dart-define=API_BASE_URL=…` and walk: onboarding → OTP → setup → home → edit profile → logout → relaunch offline.
5. Things to look at on a real phone: Lalezar/Tajawal rendering, the code boxes with the keyboard open, the city picker with the keyboard, small screens (≤ 360dp wide).

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

**Verification** (built in M2)
- documents: owner (morph: user / driver_profile / vehicle), kind (selfie, passport, national_id_front, national_id_back, driving_licence, vehicle_front/back/side/interior, vehicle_registration), disk, path, mime, size. Unique per owner+kind
- verification_reviews: subject (morph: user / driver_profile / vehicle), status (pending/approved/rejected/info_requested), id_document_type (passport/national_id, when identity is included), priority, reasons (json), note, assigned_to/assigned_at (reviewer claim), reviewed_by, reviewed_at

**Drivers & trips**
- driver_profiles (built in M2): user_id (unique), status (draft/pending_review/rejected/active/suspended/frozen), free_bookings_remaining, balance_owed, wallet_balance, settlement_due_at, frozen_at, rating_avg, rating_count, terms_accepted_at, submitted_at, approved_at
- vehicles (built in M2): driver_profile_id, type (car/boksi/minibus/bus/pickup), make, model, color, year?, plate_number, plate_normalized, seats (passengers, excl. driver), status (draft/pending/approved/rejected), submitted_at, approved_at, soft deletes
- trips (built in M3; also cancelled_at, reminded_evening_at, reminded_soon_at): driver_profile_id, vehicle_id, route_id, departs_at, est_arrival_at?, price_per_seat, seats_total, seats_available, luggage_large, luggage_small, extra_luggage_price?, accepts_parcels, parcel_price_small/medium/large, max_parcels, women_only, notes, status (scheduled/departed/completed/cancelled), cancel_reason, departed_at, completed_at, hidden_by_admin
- trip_points (built in M3): trip_id, kind (pickup/dropoff), label, lat?, lng?, sort
- bookings (built in M3; also responded_at, rejection_reason, cancel_reason, no_show_dispute_details, no_show_confirmed_at, no_show_resolution, no_show_resolved_by, completed_at, reminded_evening_at, reminded_soon_at): trip_id, passenger_id, seats, pickup_point_id, extra_bags, includes_family, message, status (pending/accepted/rejected/expired/cancelled_by_passenger/cancelled_by_driver/completed/no_show), price_total, fee_amount, fee_free, expires_at, last_reminded_at, accepted_at, cancelled_at, late_cancellation, no_show_at, no_show_disputed_at
- parcels (built in M5): trip_id, sender_id, description, size, photo_path, receiver_name, receiver_phone, payer (sender/receiver), price, fee_amount, fee_free, delivery_code (encrypted), status (pending/accepted/picked_up/delivered/cancelled/disputed), expires_at, pickup_photo_path, picked_up_at, delivered_at, refusal_reason
- route_alerts (built in M3; also last_notified_at, notified_count): user_id, origin_city_id, destination_city_id, date?, active
- search_logs (built in M3): origin_city_id, destination_city_id, route_id?, date, kind (trip/parcel), results_count, user_id?
- users.booking_restricted_until (M3)

**Money** (built in M4)
- ledger_entries (append-only): driver_profile_id, type (fee/fee_reversal/payment/wallet_topup/adjustment), bucket (balance/wallet), amount (signed), balance_after, wallet_after, reference (morph: booking / settlement_request), note, created_by_admin_id?
- payment_methods: name, account_name, account_number, instructions, agent_locations (json), requires_reference, enabled, sort
- settlement_requests: driver_profile_id, payment_method_id, purpose (settlement/wallet_topup), amount, reference_number, receipt_disk, receipt_path, status (pending/confirmed/rejected), reviewed_by, reviewed_at, reject_reason
- bookings.fee_model, bookings.fee_reversed_at; driver_profiles.settlement_reminded_at

**Trust & comms** (built in M5)
- conversations: subject (morph: booking/parcel, unique), customer_id, driver_user_id, customer_last_read_at, driver_last_read_at, last_message_at, has_flagged
- messages: conversation_id, sender_id, body (as shown), original_body (encrypted, only when masked), was_masked, flagged, flag_reason
- ratings: rater_id, ratee_id, subject (morph), role (as_driver/as_passenger — the ratee's role), stars, comment, tags (json), hidden. Unique per rater+subject
- reports: reporter_id, reported_user_id, subject (morph: booking/parcel/trip/conversation, nullable), reason, details, priority, status (open/in_review/resolved), resolution_note, handled_by, handled_at
- enforcement_actions: user_id, action (warn/suspend/ban/reactivate), until?, reason, report_id?, admin_id
- user_blocks: blocker_id, blocked_id (unique pair)
- parcels: also fee_model, fee_reversed_at, code_attempts, prohibited_ack_at, last_reminded_at, accepted_at, rejection_reason, pickup_photo_disk/path, cancelled_at, cancel_reason, receiver_notified_at, disputed_at, dispute_details, dispute_resolution, dispute_resolved_by; statuses add rejected / expired / refused
- driver_profiles.low_rating_flagged_at

**Notifications** (built in M6)
- notification_channels: event (unique), push, whatsapp, email
- notification_logs: user_id?, phone?, event, channel (push/whatsapp/email), status (sent/failed/skipped), title, error, broadcast_id?, created_at
- broadcasts: admin_id, segment, filters (json: city_id / origin_city_id + destination_city_id), channels (json), title, body, recipients_count, status (queued/sending/sent/failed), sent_at

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
- 2026-11-09 (M2): Identity documents belong to the user and are shared by passenger-ID verification and driver registration (no double upload). A driver application includes identity unless already verified.
- 2026-11-09 (M2): Driver status gained `draft` (registration started, documents uploaded one by one — better on weak connections than one big upload). `rejected` is not final: the driver fixes and resubmits. Permanent refusal = ban the user.
- 2026-11-09 (M2): "Request more info" behaves like a rejection for the user (they can edit and resubmit) but is recorded as `info_requested` with a required note.
- 2026-11-09 (M2): Rejection reasons are an admin-editable list (setting `verification.rejection_reasons`), per spec 1.6.
- 2026-11-09 (M2): Documents are photos only (jpg/png/webp, max 8 MB, setting). Image compression is the app's job (M7) to save upload data; the server stores as received.
- 2026-11-09 (M2): Vehicle registration card is labelled "شهادة البحث" (Sudanese term).
- 2026-11-09 (M2): Approved vehicles and an expired licence can't be changed from the app (support handles it) — keeps approved data trustworthy. Revisit in M10 if drivers need self-service renewal.
- 2026-11-09 (M2): Multiple reviewers: opening an item claims it for 15 minutes (soft lock, warning to others); decisions are guarded by a row lock so an item can't be decided twice.
- 2026-11-09 (M2): Created resources return HTTP 201 (Laravel default); the app should treat any 2xx as success.
- 2026-11-09 (M3): Pending booking requests don't reserve seats; only acceptance does (a driver may get more requests than seats and choose). Accepting more than available is refused.
- 2026-11-09 (M3): Before acceptance, trip pages show only the side and interior vehicle photos (front/back show the plate, which is revealed after acceptance).
- 2026-11-09 (M3): Only cancelling an *accepted* booking late counts as a late cancellation; withdrawing a pending request is free.
- 2026-11-09 (M3): Frozen drivers' trips disappear from search; their accepted bookings stay valid (spec 5.5). They can still reject requests and manage existing trips.
- 2026-11-09 (M3): Recurring trips (D7 "repeat trip") left for Phase 2 as per the roadmap.
- 2026-11-09 (M3): API times are returned in Sudan time with offset, so the app shows them as-is without timezone logic.
- 2026-11-09 (M3): Driver repeated cancellations are surfaced to admin (count on driver page) rather than auto-suspending; suspension stays a human decision.
- 2026-11-09 (M4): The weekly cycle starts at the first unpaid fee (due = that moment + 7 days), not on a fixed weekday — fair for drivers who join mid-week. Revisit if finance prefers one common settlement day.
- 2026-11-09 (M4): Unfreezing requires the full balance to be paid; a partial payment keeps the account frozen and the due date unchanged.
- 2026-11-09 (M4): Reaching the balance limit only blocks accepting new bookings; it doesn't freeze the account or hide trips.
- 2026-11-09 (M4): Commission is on seat price only (seats × price per seat); extra-luggage payments are not commissioned.
- 2026-11-09 (M4): Per-trip fee mode charges the first accepted booking; if it's reversed the fee moves to another accepted booking of the same trip.
- 2026-11-09 (M4): Refunding a fee that the driver already paid goes to the wallet as credit (no cash refunds through the app).
- 2026-11-09 (M4): The balance-limit check runs before free bookings are applied (in practice free bookings happen while the balance is 0).
- 2026-11-09 (M4): Payment methods are seeded with placeholder account details; finance fills real numbers in admin before launch.
- 2026-11-09 (M5): The receiver gets the WhatsApp code when the driver accepts the parcel (so they know who's coming), not at pickup.
- 2026-11-09 (M5): The "3 free bookings" counter covers parcels too (one counter for any accepted booking or parcel).
- 2026-11-09 (M5): Wrong delivery codes lock after 5 attempts; support confirms delivery manually (prevents guessing the 4-digit code).
- 2026-11-09 (M5): Chat masking only hides phone-like numbers (valid Sudanese number, international, or 9+ digits) and messaging links; dates, times and prices stay visible. Keyword matches are flagged for review, not blocked.
- 2026-11-09 (M5): Admins can open a chat only from a report or an automatic flag, and every opening is written to the audit log (spec 5.8).
- 2026-11-09 (M5): Passenger ratings are stored on `users.rating_avg`, driver ratings on `driver_profiles.rating_avg`, so a person's passenger and driver reputations stay separate.
- 2026-11-09 (M5): Banning a driver cancels their upcoming trips automatically (passengers notified, fees reversed); banning a passenger cancels their bookings without late-cancellation penalties.
- 2026-11-09 (M5): Chat uses polling + push notifications (no websockets) to keep the backend simple and work on weak connections.
- 2026-11-10 (M6): WhatsApp uses 4 templates instead of one per event: `musafir_otp`, `musafir_notice` (generic title + text), `musafir_parcel_code`, `musafir_announcement`. One approval covers every event; any event can be mapped to its own template later in `config/whatsapp.php` without code changes.
- 2026-11-10 (M6): Notifications are queued (database queue) with 2 retries; OTP is the only message sent inline. Production therefore needs a queue worker.
- 2026-11-10 (M6): The notification log stores the title and the provider's error but not the message body (bodies can contain delivery codes and phone numbers). Kept 60 days by default.
- 2026-11-10 (M6): The channel matrix is its own table and admin page (not 100 switches inside the settings page). Broadcast channels are chosen per message and ignore the matrix.
- 2026-11-10 (M6): Broadcasts and the channel matrix are super-admin only (WhatsApp costs money per message); Support can read the notification log.
- 2026-11-10 (M6): FCM is called directly over HTTP v1 with a hand-signed JWT, so there is no Firebase/Google SDK dependency on the server.
- 2026-11-10 (M6): Spec 13 open question 2 says WhatsApp is "charged per conversation"; Meta's pricing should be re-checked before launch (the user to confirm the current rate card for Sudan), since it decides which events keep WhatsApp on.
- 2026-11-10 (M7): State management Riverpod 2.x, navigation go_router, HTTP Dio. Text lives in one Dart class (`S`) while the app is Arabic-only; move to ARB files when English is added.
- 2026-11-10 (M7): App wording is Sudanese colloquial ("ماشي وين؟", "حاول تاني") rather than formal Arabic — to be confirmed by the user from the previews.
- 2026-11-10 (M7): Digits are always Western (0-9) in the app, as in the admin panel.
- 2026-11-10 (M7): Offline detection has no extra plugin: a failed request for lack of connection turns the offline banner on, the next successful one turns it off.
- 2026-11-10 (M7): Profile photos are resized to 1080px / quality 78 on the phone before upload (image_picker), no separate compression package.
- 2026-11-10 (M7): Input and app-bar styling is applied per widget (`AppTheme.input()`) instead of theme-level `inputDecorationTheme` / `appBarTheme` / `cardTheme`, whose types changed across recent Flutter versions.
- 2026-11-10 (M7): The home search form is part of M7 (it exercises the city picker and cache); results, trip details and booking are M8.
- 2026-11-10 (M7): Per-user notification preferences (spec P11) still undecided — not built.
- 2026-11-10 (M8): Trip times are shown exactly as the server sends them (Sudan time), never converted to the phone's timezone.
- 2026-11-10 (M8): ID photos are taken with the camera only (no gallery) — a live selfie and a photo of the real document, as the spec asks.
- 2026-11-10 (M8): "Share trip with family" uses WhatsApp (`wa.me/?text=`) or copy-to-clipboard instead of the system share sheet — no extra plugin, and WhatsApp is what people use.
- 2026-11-10 (M8): The booking page polls every 30 seconds while pending (same no-websocket approach as chat); push will make it instant once Firebase is set up.
- 2026-11-10 (M8): Filters include time of day, vehicle type, driver rating and women-only; a max-price filter is supported by the API but left out of the UI (the "cheapest" sort covers it).
- 2026-11-10 (M9): Parcels live in a 4th tab of "حجوزاتي" rather than a separate bottom-nav item.
- 2026-11-10 (M9): Chat polls every 6 seconds while a conversation is open and not at all otherwise (the unread badge refreshes when tabs are switched); push takes over once Firebase is configured.
- 2026-11-10 (M9): "Send details to receiver" opens WhatsApp directly on the receiver's number with the server's ready-made message — the manual fallback the spec asks for when the automatic message doesn't arrive.
- 2026-11-10 (M10): M10 split into M10a (registration), M10b (trips and requests), M10c (parcels, balance, payments, rating passengers).
- 2026-11-10 (M10a): Driver registration is a single checklist screen the driver can leave and resume, not a 3-step wizard — each photo is saved on the server as soon as it is taken.
- 2026-11-10 (M10a): Licence and vehicle photos are camera-only, like ID photos.
- 2026-11-10 (M10b): The passenger/driver switch is a visible toggle at the top of the main screen (drivers only) rather than a setting buried in the profile.
- 2026-11-10 (M10b): Booking requests are answered inline on cards (driver home and trip page) — no separate request-detail screen.
- 2026-11-10 (M10b): Pickup / drop-off points are typed as place names only in v1 of the app; map pins (already supported by the API) can be added later.
- 2026-11-10 (M10c): Payment receipts are picked from the gallery (they are usually banking-app screenshots); every other document photo in the app is camera-only.
- 2026-11-10 (M10c): Parcels are handled on cards inside the trip page rather than on separate request / pickup / delivery screens.
