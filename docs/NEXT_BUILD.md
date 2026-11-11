# Next build — fixes waiting for an APK

App (Flutter) fixes collect here until the user says "build". Backend fixes deploy on push and are listed only if the user needs to know. When an APK is built, move its items under "Shipped" with the release tag.

## Waiting
- [x] Home: "طرق مطلوبة" chips were solid green road signs ("too much green, hurts the eyes"). Now light chips: white, thin border, dark-green text, amber arrow. (`mobile/lib/features/home/home_screen.dart`, `_PopularRoutes`) — reported 2026-11-11

## Shipped
- apk-2 (2026-11-11): first build pointed at https://musafir.statement-ds.com/api/v1
