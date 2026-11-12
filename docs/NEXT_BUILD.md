# Next build — fixes waiting for an APK

App (Flutter) fixes collect here until the user says "build". Backend fixes deploy on push and are listed only if the user needs to know. APK builds are manual (`android-apk.yml`, workflow_dispatch); the app defaults to https://musafir.statement-ds.com/api/v1. When an APK is built, move its items under "Shipped" with the release tag.

## Waiting
- (nothing yet)

## Open questions
- Admin verification images and car photos in the app didn't show for the user (round 1). Could be the slow connection; re-check in round 2 (checklist step 26). If still missing, read the server log (needs the user's OK — production log reads were blocked in session 7).
- Real push (instant notifications when the app is closed) needs a Firebase project from the user. Until then the app polls `GET me/badges` every 12s while open.

## Shipped
- apk-6 (2026-11-12): trusted-phone login (no code on a phone that verified before; code again after `auth.trusted_device_days` days without opening the app, or on a new phone), login throttling per phone (test numbers unlimited) — the "can't confirm the code" bug was the old 10-per-minute-per-connection limit shared by both phones on the same Wi-Fi. First build signed with the shared test key (`mobile/tooling/test-debug.keystore`): uninstall once, later builds install over it.
- apk-5 (2026-11-12), round-1 test fixes: bottom bar on every page (ShellRoute), slim top bar (logo, mode pill, avatar), driver mode sticky per account and switched from the top bar, live updates + badges (`me/badges`), "booking accepted" cards, swipeable rating cards, finish-trip parcel check → driver home, parcel-first flow (details → trips with room for that size → prefilled confirm), welcome-back login + instant logout, WhatsApp-style chat outbox with ticks, driver terms link, auto-confirm of test codes, light popular-route chips.
- apk-3, apk-4: built by mistake by the old push trigger with the emulator address — don't install.
- apk-2 (2026-11-11): first build pointed at the live server.
