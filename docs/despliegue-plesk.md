# Despliegue en Plesk Obsidian (Laravel 10)

Guía operativa. El cifrado de montos queda fuera de alcance; aquí solo hosting.

## Requisitos del plan

| Requisito | Valor |
|-----------|--------|
| PHP | **8.1+** (ideal 8.2/8.3), extensiones: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| MySQL / MariaDB | Base y usuario creados en Plesk (no uses `root`) |
| Document Root | **`…/httpdocs/public`** (recomendado) |
| SSL | Let’s Encrypt en el dominio (Plesk → SSL/TLS) |
| SSH | Muy recomendable para `artisan` / `composer` |

Si el plan no permite cambiar el Document Root, el `.htaccess` de la raíz reescribe hacia `public/` y deniega `app/`, `vendor/`, `.env`, etc. Es un parche: **sigue siendo peor** que apuntar a `/public`.

## Checklist en el panel

1. Dominio → **Hosting Settings** → Document Root = `httpdocs/public` (o la ruta real del repo + `/public`).
2. **PHP** → 8.2 (o 8.3) como handler FPM; aplica límites razonables (memoria ≥ 256M).
3. **Databases** → crea DB + usuario; anota host (casi siempre `localhost`), nombre, user, password.
4. **SSL/TLS** → certificado + “Redirect from HTTP to HTTPS” si el panel lo ofrece.
5. Desactiva **phpMyAdmin** público o restríngelo; no es parte del runtime de la app.
6. **Git** (opcional): despliegue desde el repo hacia el directorio del dominio; el hook post-deploy debe correr `scripts/deploy-plesk.sh` o equivalente.

## Variables `.env` en producción

Copia `.env.example` → `.env` en el servidor (nunca lo subas al Git).

```env
APP_NAME=Finanzas
APP_ENV=production
APP_KEY=base64:...   # php artisan key:generate --show  (o key:generate en el server)
APP_DEBUG=false
APP_URL=https://tu-dominio.com
FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
LOG_LEVEL=error

MAIL_MAILER=smtp   # o el que use Plesk
APP_TIMEZONE=America/Bogota
```

Tras cambiar `.env`: `php artisan config:cache`.

## Dos flujos de subida

### A) Con SSH (preferido)

1. Sube el código (Git pull o SFTP) **sin** `.env` de local.
2. En el servidor: crea `.env`, `composer install --no-dev` (si no viene `vendor`).
3. Asegura `public/build` (Vite). Si no hay Node en el servidor, genera en local con `npm run build` y sube `public/build/`.
4. Ejecuta:

```bash
bash scripts/deploy-plesk.sh
```

Eso corre migrate, `storage:link`, `config|route|view:cache`.

Permisos típicos (usuario del dominio Plesk):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### B) Sin Node en el servidor (empaquetado local)

En tu máquina:

```bash
bash scripts/empaquetar-release.sh
```

Sube `finanzas-release.tar.gz`, extrae en el directorio del sitio, crea `.env`, luego:

```bash
bash scripts/deploy-plesk.sh
```

El tarball incluye `vendor` y `public/build`; **no** incluye `.env`.

## Qué no hacer

- Dejar `APP_DEBUG=true` en producción (filtra `.env`, rutas, SQL).
- Apuntar el Document Root a la raíz del repo **si puedes evitarlo**.
- Subir `.env` de desarrollo, `node_modules/`, o `public/hot` (rompe Vite en prod: obliga HMR).
- Confiar en `php_value` del `.htaccess`: en Plesk FPM manda `.user.ini` / PHP Settings.
- Esperar colas en background: `QUEUE_CONNECTION=sync` (fase 1). Cron de `schedule:run` solo si más adelante hay scheduler.

## Verificación rápida

1. `https://tu-dominio.com` → login/registro.
2. Assets: CSS/JS de Vite y `public/assets` cargan (sin 404 en `/build/assets/...`).
3. Cookies de sesión con flag Secure (DevTools).
4. Un ingreso/gasto de prueba y dashboard con saldos.
5. `storage/logs/laravel.log` sin errores de permisos.

## Fallos frecuentes

| Síntoma | Causa probable |
|---------|----------------|
| 500 tras subir | Falta `APP_KEY`, permisos en `storage/`, o PHP &lt; 8.1 |
| CSS/JS rotos | Falta `public/build` o quedó `public/hot` |
| Login no persiste / mixed content | `TrustProxies` / `APP_URL` http / sin `SESSION_SECURE_COOKIE` |
| 404 en todas las rutas | Document Root mal; o rewrite Apache desactivado |
| “No application encryption key” | `APP_KEY` vacío |
| Migrate falla | Credenciales DB o usuario sin privilegios DDL |

## Código ya preparado en el repo

- `TrustProxies`: confía en el proxy de Plesk (`X-Forwarded-*`).
- `FORCE_HTTPS` / `APP_ENV=production` → `URL::forceScheme('https')`.
- `.htaccess` raíz: fallback si el docroot no es `/public`.
- `public/.user.ini`: límites PHP para FPM (sustituye el bloque `mod_php` viejo).
