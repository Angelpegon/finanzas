<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\PerfStamp;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RecordPerformance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TimedStartSession;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use App\Support\PerfProbe;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\FrameGuard;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    protected $middleware = [
        RecordPerformance::class, // DIAGNÓSTICO TEMPORAL
        TrustProxies::class,
        \App\Http\Middleware\StripUrlPrefix::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        FrameGuard::class,
        PerfStamp::class.':B_global_done', // DIAGNÓSTICO TEMPORAL
    ];

    protected $middlewareGroups = [
        'web' => [
            PerfStamp::class.':C_web_enter', // DIAGNÓSTICO TEMPORAL
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            TimedStartSession::class, // DIAGNÓSTICO TEMPORAL (reemplaza StartSession)
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ],
    ];

    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
    ];

    /** DIAGNÓSTICO TEMPORAL: mide el boot de providers (suele ser el tramo frío). */
    public function bootstrap(): void
    {
        $start = microtime(true);
        $GLOBALS['__perf_marks']['A_providers_boot_start'] = $start;
        PerfProbe::mark('A_providers_boot_start');
        parent::bootstrap();
        $end = microtime(true);
        $GLOBALS['__perf_marks']['A_providers_boot_end'] = $end;
        PerfProbe::mark('A_providers_boot_end');
    }
}
