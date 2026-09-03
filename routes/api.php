<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/usuario', fn (\Illuminate\Http\Request $request) => $request->user());
