#!/usr/bin/env bash
# Empaqueta un release listo para subir a Plesk (sin .env, sin node_modules).
# Genera public/build y vendor --no-dev en este máquina.
# Uso: bash scripts/empaquetar-release.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUT="${1:-finanzas-release.tar.gz}"

echo "==> npm ci && npm run build"
npm ci
npm run build

echo "==> composer install --no-dev"
composer install --no-dev --optimize-autoloader --no-interaction

# Nunca empaquetar caches de artisan: config:cache/route:cache/view:cache
# congelan rutas absolutas de ESTA máquina (p. ej. /Users/...) y rompen open_basedir en Plesk.
echo "==> limpiando bootstrap/cache (excepto .gitignore)"
find bootstrap/cache -type f ! -name '.gitignore' -delete 2>/dev/null || true

echo "==> tar $OUT"
tar -czf "$OUT" \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='.phpunit.result.cache' \
  --exclude='.cursor' \
  --exclude='*.tar.gz' \
  --exclude='bootstrap/cache/*.php' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='public/diag-*.php' \
  --exclude='public/diag*.php' \
  \
  app artisan bootstrap config database docs lang public resources routes \
  scripts storage \
  composer.json composer.lock package.json package-lock.json vite.config.js \
  .htaccess AGENTS.md \
  vendor

echo "Creado: $OUT"
echo "En el servidor: extrae en httpdocs, crea .env, luego bash scripts/deploy-plesk.sh"
echo "(Si ya trajiste vendor, deploy-plesk solo hará migrate + caches.)"
