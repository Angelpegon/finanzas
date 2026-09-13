Necesito que realices un diagnóstico técnico del rendimiento de este proyecto Laravel.

CONTEXTO DEL PROBLEMA
=====================

El proyecto está alojado en Plesk y se accede desde:

https://ingeer.co/finanzas

El proyecto funciona normalmente y las solicitudes posteriores son rápidas.

Sin embargo, después de aproximadamente 30 minutos sin acceder al proyecto, al volver a abrirlo existe una demora inicial de aproximadamente 3 a 5 segundos.

Después de esa primera carga, el proyecto vuelve a funcionar rápidamente.

IMPORTANTE:
No asumas que el problema es PHP-FPM, MySQL o Laravel sin evidencia.
El objetivo es encontrar exactamente dónde ocurre la demora.

PRUEBAS QUE YA SE REALIZARON
============================

1. PHP directo

Se creó temporalmente:

public/test.php

con código PHP simple.

Después de más de 30 minutos sin acceder al proyecto:

https://ingeer.co/finanzas/test.php

carga instantáneamente.

CONCLUSIÓN:
PHP/Plesk/FPM básico aparentemente no es la causa directa.

--------------------------------------------------

2. Prueba Laravel + MySQL

Se creó una ruta temporal que ejecuta:

DB::select('SELECT 1');

La ruta responde prácticamente instantáneamente.

La medición interna de MySQL fue aproximadamente:

125.5 ms

CONCLUSIÓN:
MySQL no parece explicar una demora de 3 a 5 segundos.

--------------------------------------------------

3. Ruta principal

La ruta principal es:

Route::match(['GET', 'HEAD', 'POST'], '/', HomeController::class)
    ->name('home');

HomeController:

public function __invoke(): RedirectResponse
{
    return redirect()->route(
        Auth::check() ? 'app.situacion' : 'login'
    );
}

--------------------------------------------------

4. Login

La ruta:

/login

usa:

public function loginForm(): View
{
    return view('auth.login');
}

Este método no realiza consultas ni llamadas externas.

El problema de demora ocurre también al entrar al LOGIN después de un período de inactividad.

Esto es importante porque descarta que el problema esté exclusivamente en los controladores autenticados.

--------------------------------------------------

5. Configuración relevante

APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=localhost

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

MAIL_MAILER=log

--------------------------------------------------

6. Middleware global

El proyecto tiene middleware personalizados, incluyendo:

- TrustProxies
- StripUrlPrefix
- FrameGuard
- SecurityHeaders

El grupo web contiene:

- EncryptCookies
- AddQueuedCookiesToResponse
- StartSession
- ShareErrorsFromSession
- VerifyCsrfToken
- SubstituteBindings

OBJETIVO DEL DIAGNÓSTICO
========================

Analiza el proyecto completo buscando posibles causas de una demora inicial después de un período de inactividad.

NO realices cambios destructivos ni modifiques la arquitectura inicialmente.

Primero realiza una auditoría.

Revisa especialmente:

1. Middleware globales personalizados
   - StripUrlPrefix
   - TrustProxies
   - FrameGuard
   - SecurityHeaders

2. Middleware relacionados con:
   - sesión
   - autenticación
   - cookies

3. Service Providers:
   - AppServiceProvider
   - otros providers personalizados

4. View composers y código ejecutado globalmente.

5. Blade layouts utilizados por el login.

6. Recursos externos cargados en el login:
   - Google Fonts
   - CDN
   - Bootstrap CDN
   - Font Awesome
   - scripts externos
   - imágenes externas

7. Funcionalidad PWA:
   - Service Worker
   - manifest
   - boot splash screen
   - scripts de registro del Service Worker
   - estrategias de caché

8. Cualquier:
   - file_get_contents remoto
   - Http::get()
   - Curl
   - llamadas API
   - DNS lookup
   - conexión externa
   - comprobación de IP
   - validación remota

9. Autoloaders o código ejecutado durante el bootstrap.

10. Configuración que pueda provocar delays después de períodos de inactividad.

METODOLOGÍA REQUERIDA
=====================

No quiero conclusiones basadas únicamente en lectura de código.

Implementa temporalmente instrumentación de rendimiento para medir el tiempo de ejecución.

Debes identificar cuánto tarda cada etapa:

A. Entrada de la petición
B. Middleware global
C. Middleware web
D. Sesión
E. Middleware guest
F. Controlador loginForm
G. Renderizado de la vista
H. Recursos PWA si pueden analizarse desde código

Utiliza microtime(true) o una estrategia equivalente.

Agrega logs temporales claros con timestamps y duración en milisegundos.

Por ejemplo:

[PERFORMANCE]
request_start: X ms
middleware: X ms
controller: X ms
view: X ms
total: X ms

IMPORTANTE:

La instrumentación debe poder eliminarse fácilmente después del diagnóstico.

También analiza los logs existentes en:

storage/logs/

Busca:

- errores
- timeouts
- conexiones lentas
- excepciones
- reintentos
- problemas relacionados con sesión

RESULTADO ESPERADO
==================

Al finalizar NO hagas cambios grandes automáticamente.

Entrega un informe con:

1. Archivos revisados.
2. Posibles causas encontradas.
3. Evidencia concreta para cada sospecha.
4. Mediciones disponibles.
5. Ranking de probabilidad de las causas.
6. Recomendación de pruebas adicionales.
7. Cambios mínimos recomendados para corregir el problema.

Solo después de identificar una causa con evidencia, propone la implementación de la solución.
