# Next build — fixes waiting for an APK

App (Flutter) fixes collect here until the user says "build". Backend fixes deploy on push and are listed only if the user needs to know. APK builds are manual (`android-apk.yml`, workflow_dispatch); the app defaults to https://musafir.statement-ds.com/api/v1. When an APK is built, move its items under "Shipped" with the release tag.

## Waiting
- (nothing yet)

## Open questions
- Email codes need a real mailbox: the user creates noreply@statement-ds.com in hPanel and fills admin → قنوات الإشعارات → إعداد البريد. Until then only test numbers can log in.
- Push needs the Firebase key pasted once in admin → قنوات الإشعارات → إعداد Firebase (file in the user's Downloads).

## Shipped
- apk-12 (2026-11-15; build numbers 8–11 were compile checks): Firebase push (FCM) with tap-to-open and an in-app banner, high-importance channel + notification icon; login code by email (one-time email step for new accounts); app name مسافر معاك + launcher icon; versionCode = build number; older chat messages; full booking/parcel/ledger/conversation lists; blocked users screen; limited trip edit (seats up, notes); wallet top-up; home search resets per account. Backend (already live): images fixed (encrypted /api/v1/files links), Firebase key + SMTP set in admin, legal drafts.
- apk-7 (2026-11-12): code screen sized from the server's `code_length` (otp.length setting, 4–8); confirm enabled from 4 digits. The screen had assumed 6, so with a longer code it showed 6 digits and never enabled confirm — the real "can't confirm" bug.
- apk-6 (2026-11-12): trusted-phone login (no code on a phone that verified before; code again after `auth.trusted_device_days` days without opening the app, or on a new phone), login throttling per phone (test numbers unlimited) — the "can't confirm the code" bug was the old 10-per-minute-per-connection limit shared by both phones on the same Wi-Fi. First build signed with the shared test key (`mobile/tooling/test-debug.keystore`): uninstall once, later builds install over it.
- apk-5 (2026-11-12), round-1 test fixes: bottom bar on every page (ShellRoute), slim top bar (logo, mode pill, avatar), driver mode sticky per account and switched from the top bar, live updates + badges (`me/badges`), "booking accepted" cards, swipeable rating cards, finish-trip parcel check → driver home, parcel-first flow (details → trips with room for that size → prefilled confirm), welcome-back login + instant logout, WhatsApp-style chat outbox with ticks, driver terms link, auto-confirm of test codes, light popular-route chips.
- apk-3, apk-4: built by mistake by the old push trigger with the emulator address — don't install.
- apk-2 (2026-11-11): first build pointed at the live server.
