<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/probar-auth', function () {
    return response()->json(['message' => 'Autenticado']);
})->middleware(\App\Http\Middleware\VerifySupabaseToken::class);
