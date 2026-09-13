<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route(Auth::check() ? 'app.situacion' : 'login');
    }
}
