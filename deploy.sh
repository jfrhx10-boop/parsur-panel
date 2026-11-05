#!/bin/bash
# Despliegue del panel Parsur en SiteGround. Lo ejecuta un cron cada pocos minutos.
# Solo actúa si hay cambios nuevos en GitHub. No toca .env, storage ni vendor.
set -u
BASE="/home/customer/www/panel.recambiosparsur.com"
REPO="$BASE/repo"
APP="$BASE/app"
PUB="$BASE/public_html"
LOG="$BASE/deploy.log"
PHP="${PHP_BIN:-php}"

# Copia de seguridad diaria de la base de datos del panel (se conservan 14 días).
DB="$APP/database/database.sqlite"
BK="$BASE/backups"
if [ -f "$DB" ]; then
  mkdir -p "$BK"
  TODAY="$BK/panel-$(date '+%F').sqlite"
  if [ ! -f "$TODAY" ]; then
    if command -v sqlite3 >/dev/null 2>&1; then
      sqlite3 "$DB" ".backup '$TODAY'" 2>>"$LOG" || cp "$DB" "$TODAY"
    else
      cp "$DB" "$TODAY"
    fi
    gzip -f "$TODAY" 2>/dev/null
    find "$BK" -name 'panel-*.sqlite*' -mtime +14 -delete 2>/dev/null
    echo "$(date '+%F %T') copia de seguridad $(basename "$TODAY").gz" >> "$LOG"
  fi
fi

cd "$REPO" || exit 1
git fetch --quiet origin main || { echo "$(date '+%F %T') fetch falló" >> "$LOG"; exit 1; }
REMOTE=$(git rev-parse origin/main)
MARK="$BASE/.deployed_sha"
[ "$(cat "$MARK" 2>/dev/null)" = "$REMOTE" ] && exit 0

git reset --hard --quiet origin/main
for d in app bootstrap database resources routes; do
  rsync -a "$REPO/$d/" "$APP/$d/"
done
# public: no pisar index.php (lleva las rutas ajustadas a este servidor)
rsync -a --exclude 'index.php' "$REPO/public/" "$PUB/"

cd "$APP" || exit 1
$PHP artisan migrate --force >> "$LOG" 2>&1
$PHP artisan optimize:clear >> "$LOG" 2>&1
echo "$REMOTE" > "$MARK"
echo "$(date '+%F %T') desplegado $(git -C "$REPO" rev-parse --short HEAD)" >> "$LOG"
