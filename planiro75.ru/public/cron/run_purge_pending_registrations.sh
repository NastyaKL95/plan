#!/bin/sh
# Удаление старых pending_email (по умолчанию старше 14 дней).
# Crontab (раз в сутки): 15 3 * * * /bin/sh /home/LOGIN/www/planiro75.ru/public/cron/run_purge_pending_registrations.sh

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
LOG="$ROOT/var/log/reg-purge-cron.log"
WORKER="$ROOT/public/cron/purge_pending_registrations.php"

mkdir -p "$ROOT/var/log" 2>/dev/null || true

PHP="${PLANNER_PHP:-}"
if [ -z "$PHP" ]; then
  if command -v php >/dev/null 2>&1; then
    PHP="php"
  else
    for c in /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php81 /usr/bin/php82 /usr/bin/php /usr/local/bin/php /opt/php81/bin/php /opt/php82/bin/php; do
      if [ -x "$c" ]; then
        PHP="$c"
        break
      fi
    done
  fi
fi

if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
  echo "$(date -Iseconds 2>/dev/null || date) ERROR: php not found" >>"$LOG"
  exit 127
fi

if [ ! -f "$WORKER" ]; then
  echo "$(date -Iseconds 2>/dev/null || date) ERROR: worker missing: $WORKER" >>"$LOG"
  exit 1
fi

cd "$ROOT" || {
  echo "$(date -Iseconds 2>/dev/null || date) ERROR: cannot cd $ROOT" >>"$LOG"
  exit 1
}

echo "=== $(date -Iseconds 2>/dev/null || date) php=$PHP pwd=$(pwd) ===" >>"$LOG"
"$PHP" public/cron/purge_pending_registrations.php >>"$LOG" 2>&1
ec=$?
echo "exit=$ec" >>"$LOG"

exit $ec
