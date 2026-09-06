#!/usr/bin/env bash
# Post-despliegue en el servidor Plesk (SSH o Scheduled Task).
# Uso típico en cron Plesk:
#   PHP_BIN=/opt/plesk/php/8.2/bin/php /bin/bash /var/www/vhosts/DOMINIO/httpdocs/finanzas/scripts/deploy-plesk.sh
set -euo pipefail

export PATH="/usr/local/bin:/usr/bin:/bin:/opt/plesk/php/8.3/bin:/opt/plesk/php/8.2/bin:/opt/plesk/php/8.1/bin:${PATH:-}"

SCRIPT_PATH="${BASH_SOURCE[0]:-$0}"
if [[ "$SCRIPT_PATH" != /* ]]; then
  SCRIPT_PATH="$(pwd)/$SCRIPT_PATH"
fi
# Normaliza //httpdocs → /httpdocs (cron chroot a veces deja //)
SCRIPT_PATH="${SCRIPT_PATH//\/\//\/}"
ROOT="$(cd "${SCRIPT_PATH%/*}/.." && pwd)"
ROOT="${ROOT//\/\//\/}"
cd "$ROOT"

echo "==> ROOT=$ROOT"
echo "==> PATH=$PATH"

if [[ ! -f .env ]]; then
  echo "Falta .env en $ROOT"
  echo "Crea httpdocs/finanzas/.env con APP_URL=https://ingeer.co/finanzas y reintenta."
  exit 1
fi

resolve_php() {
  local candidate
  if [[ -n "${PHP_BIN:-}" ]]; then
    if [[ -x "$PHP_BIN" ]]; then
      echo "$PHP_BIN"
      return 0
    fi
    if command -v "$PHP_BIN" >/dev/null 2>&1; then
      command -v "$PHP_BIN"
      return 0
    fi
  fi

  for candidate in \
    /opt/plesk/php/8.3/bin/php \
    /opt/plesk/php/8.2/bin/php \
    /opt/plesk/php/8.1/bin/php \
    /usr/local/bin/php \
    /usr/bin/php \
    php
  do
    if [[ -x "$candidate" ]]; then
      echo "$candidate"
      return 0
    fi
    if command -v "$candidate" >/dev/null 2>&1; then
      command -v "$candidate"
      return 0
    fi
  done

  return 1
}

if ! PHP_BIN="$(resolve_php)"; then
  echo "No se encontró PHP CLI."
  echo "En la Scheduled Task usa el comando completo, por ejemplo:"
  echo "  PHP_BIN=/opt/plesk/php/8.2/bin/php /bin/bash /var/www/vhosts/ingeer.co/httpdocs/finanzas/scripts/deploy-plesk.sh"
  echo "La versión debe coincidir con PHP Settings del dominio (8.1 / 8.2 / 8.3)."
  echo "Si /opt/plesk/... no existe en este jail, en la tarea desactiva chroot / usa 'system' shell,"
  echo "o pide SSH al hosting."
  ls -la /opt/plesk/php 2>/dev/null || echo "(no hay /opt/plesk/php visible aquí)"
  ls -la /usr/bin/php /usr/local/bin/php 2>/dev/null || true
  exit 1
fi
echo "==> PHP_BIN=$PHP_BIN"
"$PHP_BIN" -v | head -n 1

COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  echo "==> composer install --no-dev"
  "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
elif [[ -d vendor ]]; then
  echo "==> composer no disponible; se usa vendor/ empaquetado"
else
  echo "ERROR: no hay composer ni carpeta vendor/."
  exit 1
fi

if [[ -f public/hot ]]; then
  echo "==> eliminando public/hot (forzaría Vite HMR en producción)"
  rm -f public/hot
fi

if [[ ! -d public/build ]] || [[ ! -f public/build/manifest.json ]]; then
  echo "AVISO: falta public/build (Vite). Compila en local (npm run build) y súbelo."
fi

echo "==> permisos storage / bootstrap/cache"
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> migrate --force"
"$PHP_BIN" artisan migrate --force

echo "==> storage:link (idempotente)"
"$PHP_BIN" artisan storage:link 2>/dev/null || true

echo "==> caches (siempre regenerar en el servidor; nunca reutilizar las de local)"
for f in bootstrap/cache/*.php; do
  [[ -e "$f" ]] || continue
  rm -f "$f"
done
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "Listo. Verifica APP_DEBUG=false y APP_URL=https://ingeer.co/finanzas"
