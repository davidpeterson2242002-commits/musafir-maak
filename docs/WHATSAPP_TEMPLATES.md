# WhatsApp templates — مسافر معاك

WhatsApp only lets a business message someone first by using a **template** that Meta approved beforehand.
The backend sends every WhatsApp message as one of the four templates below. Create them in
**WhatsApp Manager → Message templates** (language: **Arabic — `ar`**) with exactly these names, wait for approval,
then set `WHATSAPP_DRIVER=meta` and test with `php artisan notifications:test <phone> --channel=whatsapp`.

Names and variable order are configured in `backend/config/whatsapp.php` (or the `WHATSAPP_TEMPLATE_*` env
variables) — if you name a template differently, change it there, not in code.

## 1. `musafir_otp` — category: Authentication

Login code. Choose the **Copy code** button; Meta writes the text itself for authentication templates
("‎{{1}} هو رمز التحقق الخاص بك"). Options: add the security recommendation, expiry 10 minutes.

| Variable | Value |
|---|---|
| `{{1}}` | the 6-digit code (also sent to the copy-code button) |

## 2. `musafir_notice` — category: Utility

Used for every account/booking notification that has WhatsApp enabled in the admin channel matrix
(booking accepted, trip cancelled, driver approved, account frozen, …).

```
مسافر معاك: {{1}}

{{2}}

افتح التطبيق لمعرفة التفاصيل.
```

| Variable | Value | Sample for the review form |
|---|---|---|
| `{{1}}` | notification title | تم قبول حجزك |
| `{{2}}` | notification text (line breaks become " — ") | السائق أحمد محمد — 0912345678 — كورولا بيضاء، لوحة خ 12345 — الانطلاق 2026-12-01 07:00 من موقف شندي |

## 3. `musafir_parcel_code` — category: Utility

Sent to a parcel's receiver (who may not have the app) when the driver accepts the ارسالية.

```
لديك ارسالية من {{1}} عبر مسافر معاك.

{{2}}

رمز التسليم: {{3}}
لا تعطِ الرمز للسائق إلا بعد استلام الارسالية.
```

| Variable | Value | Sample |
|---|---|---|
| `{{1}}` | sender name | منى عثمان |
| `{{2}}` | driver, car, arrival date, who pays | السائق: أحمد محمد — 0912345678 — العربية: تويوتا هايس، لوحة خ 12345 — الوصول المتوقع: 2026-12-01 إلى مدني — ادفع للسائق 5,000 جنيه عند الاستلام. |
| `{{3}}` | 4-digit delivery code | 4821 |

## 4. `musafir_announcement` — category: Marketing

Only used when an admin ticks "واتساب" on a broadcast (الإشعارات الجماعية). Optional: skip it if you
will only broadcast by push and email.

```
إعلان من مسافر معاك: {{1}}

{{2}}

شكراً لاستخدامك مسافر معاك.
```

| Variable | Value | Sample |
|---|---|---|
| `{{1}}` | broadcast title | الطريق مفتوح |
| `{{2}}` | broadcast text | طريق الخرطوم — مدني عاد للعمل، والرحلات متاحة من اليوم. |

## Notes

- **Rules Meta enforces on variables** (handled by the backend): no line breaks, no long runs of spaces, never empty.
  A template can't start or end with a variable, which is why each one has fixed text around them.
- **`musafir_notice` is deliberately generic** so one approval covers ~10 kinds of message. If Meta rejects it or
  re-labels it as Marketing (which costs more), create a specific template for the events you care about and map it:
  add e.g. `'booking_accepted' => ['name' => 'musafir_booking_accepted', 'params' => ['title', 'body']]` under
  `templates` in `config/whatsapp.php`.
- **Cost:** WhatsApp charges for business-initiated template messages, with different rates for
  Authentication / Utility / Marketing and per destination country. Check Meta's current rate card for Sudan before
  enabling WhatsApp on high-volume events; the admin channel matrix is where you turn events on and off.
- **Errors** from WhatsApp (template missing, number not on WhatsApp, payment issue…) show up with Meta's error
  code in Admin → سجل الإشعارات.
