# Next build — fixes waiting for an APK

App (Flutter) fixes collect here until the user says "build". Backend fixes deploy on push and are listed only if the user needs to know. APK builds are manual (`android-apk.yml`, workflow_dispatch); the app defaults to https://musafir.statement-ds.com/api/v1. When an APK is built, move its items under "Shipped" with the release tag.

## Waiting
- (nothing yet)

## Open questions
- Admin verification images and car photos in the app didn't show for the user (round 1). Could be the slow connection; re-check in round 2 (checklist step 26). If still missing, read the server log (needs the user's OK — production log reads were blocked in session 7).
- Real push (instant notifications when the app is closed) needs a Firebase project from the user. Until then the app polls `GET me/badges` every 12s while open.

## Shipped
- apk-5 (2026-11-12), round-1 test fixes: bottom bar on every page (ShellRoute), slim top bar (logo, mode pill, avatar), driver mode sticky per account and switched from the top bar, live updates + badges (`me/badges`), "booking accepted" cards, swipeable rating cards, finish-trip parcel check → driver home, parcel-first flow (details → trips with room for that size → prefilled confirm), welcome-back login + instant logout, WhatsApp-style chat outbox with ticks, driver terms link, auto-confirm of test codes, light popular-route chips.
- apk-3, apk-4: built by mistake by the old push trigger with the emulator address — don't install.
- apk-2 (2026-11-11): first build pointed at the live server.
