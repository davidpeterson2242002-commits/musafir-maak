#!/usr/bin/env bash
# Runs ON the Hostinger server (piped over SSH by .github/workflows/deploy-backend.yml).
# Expects ~/musafir-backend.tgz and env: DB_PASSWORD, APP_URL, DB_NAME, DB_USER,
# optional ADMIN_EMAIL / ADMIN_PASSWORD.
set -euo pipefail

BASE="$HOME/musafir"
APP="$BASE/app"
SHARED="$BASE/shared"
WEBROOT="$HOME/domains/statement-ds.com/public_html/musafir"

# --- PHP 8.3+ binary -------------------------------------------------------
PHP=""
for c in /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php "$(command -v php || true)"; do
  if [ -n "$c" ] && [ -x "$c" ] && "$c" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then PHP="$c"; break; fi
done
[ -n "$PHP" ] || { echo "No PHP >= 8.3 found"; exit 1; }
echo "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

# --- shared storage + .env (created once) ----------------------------------
mkdir -p "$SHARED"/storage/{app/private,app/public,framework/cache/data,framework/sessions,framework/views,logs}
FIRST_DEPLOY=0
if [ ! -f "$SHARED/.env" ]; then
  FIRST_DEPLOY=1
  [ -n "${DB_PASSWORD:-}" ] || { echo "DB_PASSWORD secret is empty"; exit 1; }
  cat > "$SHARED/.env" <<EOF
APP_NAME="Musafir Maak"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=$APP_URL
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=ar_SA
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=daily
LOG_LEVEL=info
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD="$DB_PASSWORD"
SESSION_DRIVER=database
SESSION_LIFETIME=120
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
QUEUE_VIA_SCHEDULER=true
CACHE_STORE=database
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@statement-ds.com"
MAIL_FROM_NAME="\${APP_NAME}"
WHATSAPP_DRIVER=log
WHATSAPP_TEMPLATE_LANGUAGE=ar
PUSH_DRIVER=log
PRIVATE_DISK=local
SIGNED_URL_MINUTES=30
# TESTING PHASE ONLY: WhatsApp is not connected yet, so the login code is returned
# by the API and filled in by the app. Set to false once WhatsApp works.
OTP_EXPOSE_IN_RESPONSE=true
ADMIN_EMAIL=${ADMIN_EMAIL:-}
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
EOF
  chmod 600 "$SHARED/.env"
  echo "Created $SHARED/.env"
fi

# --- unpack new release -----------------------------------------------------
rm -rf "$BASE/app_new"
mkdir -p "$BASE/app_new"
tar -xzf "$HOME/musafir-backend.tgz" -C "$BASE/app_new"
ln -sfn "$SHARED/.env" "$BASE/app_new/.env"
ln -sfn "$SHARED/storage" "$BASE/app_new/storage"
mkdir -p "$BASE/app_new/bootstrap/cache"

cd "$BASE/app_new"
if ! grep -q '^APP_KEY=base64:' "$SHARED/.env"; then
  "$PHP" artisan key:generate --force
fi
"$PHP" artisan package:discover --ansi >/dev/null

# Swap releases (keep the previous one for a quick manual rollback).
rm -rf "$BASE/app_prev"
[ -d "$APP" ] && mv "$APP" "$BASE/app_prev"
mv "$BASE/app_new" "$APP"
cd "$APP"

"$PHP" artisan migrate --force
if [ "$FIRST_DEPLOY" = 1 ]; then
  "$PHP" artisan db:seed --force
fi
"$PHP" artisan settings:sync
"$PHP" artisan notifications:sync
"$PHP" artisan admin:bootstrap
"$PHP" artisan storage:link --force >/dev/null 2>&1 || true
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan filament:optimize >/dev/null 2>&1 || true

# --- web root -> Laravel public/ -------------------------------------------
if [ -e "$WEBROOT" ] && [ ! -L "$WEBROOT" ]; then
  mv "$WEBROOT" "$WEBROOT.default-$(date +%s)"
fi
ln -sfn "$APP/public" "$WEBROOT"

# --- cron: scheduler every minute (timers + queue) -------------------------
CRON_LINE="* * * * * cd $APP && $PHP artisan schedule:run >> /dev/null 2>&1"
if command -v crontab >/dev/null 2>&1; then
  ( crontab -l 2>/dev/null | grep -v 'musafir/app && .* schedule:run' ; echo "$CRON_LINE" ) | crontab - \
    && echo "Cron installed." || echo "CRON NOT INSTALLED — add in hPanel: $CRON_LINE"
else
  echo "crontab not available — add in hPanel → Cron Jobs: $CRON_LINE"
fi

rm -f "$HOME/musafir-backend.tgz"
echo "Deployed $(date -u +%FT%TZ)"
