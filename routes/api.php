<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/usuario', fn (\Illuminate\Http\Request $request) => $request->user());
