<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Single Page Application Fallback)
|--------------------------------------------------------------------------
| Sirve la plataforma web compilada (Vue 3 / Vite) para el acceso de
| Daniel (Super Admin), Dueño y Contabilidad en c22.ribersoft.com.
*/

Route::get('/', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
});

// Captura cualquier ruta del frontend (SPA) para que Vue Router maneje las rutas
Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return abort(404);
})->where('any', '^(?!api).*$');
