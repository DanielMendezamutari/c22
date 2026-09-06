<?php

use App\Infrastructure\Http\Controllers\Api\TransformacionController;
use App\Infrastructure\Http\Controllers\Api\TurnoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autenticación por PIN y Bootstrap - User Story 7
    Route::get('/auth/sucursales', [\App\Infrastructure\Http\Controllers\Api\AuthController::class, 'listarSucursales']);
    Route::get('/auth/sucursales-usuarios', [\App\Infrastructure\Http\Controllers\Api\AuthController::class, 'listarSucursalesYUsuarios']);
    Route::post('/auth/login-pin', [\App\Infrastructure\Http\Controllers\Api\AuthController::class, 'loginPin']);

    // Transformaciones (Relleno y Bajas) - User Story 1
    Route::post('/transformaciones/relleno', [TransformacionController::class, 'registrarRelleno']);
    Route::post('/transformaciones/baja', [TransformacionController::class, 'registrarBaja']);

    // Cobro a Cajera y Resumen Dinámico - User Story 1
    Route::get('/turnos/{id}/resumen-cajera', [TurnoController::class, 'resumenCajera']);
    Route::post('/turnos/{id}/cobro-recibido', [TurnoController::class, 'cobroRecibido']);

    // Gestión de Turnos de 12h y Cortes de Inventario - User Story 3
    Route::post('/turnos/abrir', [TurnoController::class, 'abrirTurno']);
    Route::post('/turnos/{id}/cerrar', [TurnoController::class, 'cerrarTurno']);
    Route::get('/productos/corte', [TurnoController::class, 'productosParaCorte']);

    // Recepción de Mercadería Externa - User Story 2
    Route::post('/ingresos', [\App\Infrastructure\Http\Controllers\Api\IngresoController::class, 'registrarIngreso']);
    Route::post('/inventario/ingreso', [\App\Infrastructure\Http\Controllers\Api\IngresoController::class, 'registrarIngreso']);

    // Auditoría, Conciliación Ticket Z y Liquidación Semanal - User Story 5 & 6
    Route::post('/auditoria/calcular', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'calcularAuditoria']);
    Route::get('/liquidaciones/semanal', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'liquidacionSemanal']);
    Route::get('/auditoria/ratios', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'reporteRatios']);

    // Gestión Dinámica de Recetas y Combos - User Story 6 & F1
    Route::get('/recetas/transformacion', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'listarTransformaciones']);
    Route::post('/recetas/transformacion', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarTransformacion']);
    Route::put('/recetas/transformacion/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarTransformacion']);
    Route::get('/recetas/combos', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'listarCombos']);
    Route::post('/recetas/combos', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarCombo']);
    Route::put('/recetas/combos/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarCombo']);

    // Traspasos Inter-Sucursales - User Story 4
    Route::post('/traspasos/enviar', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'enviar']);
    Route::post('/traspasos/{id}/recibir', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'recibir']);
    Route::get('/traspasos/pendientes', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'listarPendientes']);

    // Catálogo Maestro de Productos - User Story 8
    Route::get('/productos', [\App\Infrastructure\Http\Controllers\Api\ProductoController::class, 'index']);
    Route::post('/productos', [\App\Infrastructure\Http\Controllers\Api\ProductoController::class, 'store']);
    Route::put('/productos/{id}', [\App\Infrastructure\Http\Controllers\Api\ProductoController::class, 'update']);
    Route::patch('/productos/{id}/toggle-activo', [\App\Infrastructure\Http\Controllers\Api\ProductoController::class, 'toggleActivo']);

    // Gestión Integral de Usuarios y PINs - User Story 9
    Route::get('/usuarios', [\App\Infrastructure\Http\Controllers\Api\UserController::class, 'index']);
    Route::post('/usuarios', [\App\Infrastructure\Http\Controllers\Api\UserController::class, 'store']);
    Route::put('/usuarios/{id}', [\App\Infrastructure\Http\Controllers\Api\UserController::class, 'update']);
    Route::put('/usuarios/{id}/pin', [\App\Infrastructure\Http\Controllers\Api\UserController::class, 'cambiarPin']);

    // Declaración Directa de Bajas y Roturas en Barra - User Story 10
    Route::post('/inventario/bajas', [\App\Infrastructure\Http\Controllers\Api\TransformacionController::class, 'registrarBaja']);

    // Liquidación Semanal: Registro de Pago
    Route::post('/liquidaciones/pagar', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'registrarPagoLiquidacion']);

    // Compras e Ingreso Multi-Producto - User Story 2
    Route::get('/inventario/compras', [\App\Infrastructure\Http\Controllers\Api\CompraController::class, 'index']);
    Route::post('/inventario/compras', [\App\Infrastructure\Http\Controllers\Api\CompraController::class, 'store']);

    // Auditoría: Turnos Cerrados Pendientes - User Story 5
    Route::get('/auditoria/turnos-pendientes', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'turnosPendientes']);

    // Gestión Integral de Sucursales - User Story 11
    Route::get('/sucursales', [\App\Infrastructure\Http\Controllers\Api\SucursalController::class, 'index']);
    Route::post('/sucursales', [\App\Infrastructure\Http\Controllers\Api\SucursalController::class, 'store']);
    Route::put('/sucursales/{id}', [\App\Infrastructure\Http\Controllers\Api\SucursalController::class, 'update']);
    Route::patch('/sucursales/{id}/toggle-activo', [\App\Infrastructure\Http\Controllers\Api\SucursalController::class, 'toggleActivo']);
});

