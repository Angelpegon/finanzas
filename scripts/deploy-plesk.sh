#!/usr/bin/env bash
# Post-despliegue en el servidor Plesk (SSH), desde la raíz del proyecto.
# Uso: bash scripts/deploy-plesk.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Falta .env. Copia .env.example, configura DB/APP_URL/APP_KEY y reintenta."
  exit 1
fi

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

echo "==> composer install --no-dev"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

if [[ -f public/hot ]]; then
  echo "==> eliminando public/hot (forzaría Vite HMR en producción)"
  rm -f public/hot
fi

if [[ ! -d public/build ]] || [[ ! -f public/build/manifest.json ]]; then
  echo "AVISO: falta public/build (Vite). Compila en local (npm run build) y súbelo,"
  echo "      o ejecuta npm ci && npm run build aquí si hay Node."
fi

echo "==> permisos storage / bootstrap/cache"
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> migrate --force"
$PHP_BIN artisan migrate --force

echo "==> storage:link (idempotente)"
$PHP_BIN artisan storage:link 2>/dev/null || true

echo "==> caches"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "Listo. Verifica APP_DEBUG=false y APP_URL=https://..."
