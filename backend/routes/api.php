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
    Route::get('/turnos/activo', [TurnoController::class, 'turnoActivo']);
    Route::get('/turnos/{id}/corte-inicial', [TurnoController::class, 'corteInicial']);
    Route::get('/turnos/historial-cortes', [TurnoController::class, 'historialCortes']);
    Route::get('/productos/corte', [TurnoController::class, 'productosParaCorte']);

    // Recepción de Mercadería Externa - User Story 2
    Route::post('/ingresos', [\App\Infrastructure\Http\Controllers\Api\IngresoController::class, 'registrarIngreso']);
    Route::post('/inventario/ingreso', [\App\Infrastructure\Http\Controllers\Api\IngresoController::class, 'registrarIngreso']);

    // Auditoría, Conciliación Ticket Z y Liquidación Semanal - User Story 5 & 6
    Route::post('/auditoria/calcular', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'calcularAuditoria']);
    Route::get('/liquidaciones/semanal', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'liquidacionSemanal']);
    Route::get('/auditoria/ratios', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'reporteRatios']);

    // Gestión Dinámica de Recetas y Combos - User Story 6 & 12
    Route::get('/recetas/transformacion', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'listarTransformaciones']);
    Route::post('/recetas/transformacion', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarTransformacion']);
    Route::put('/recetas/transformacion/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarTransformacion']);
    Route::delete('/recetas/transformacion/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'eliminarTransformacion']);
    Route::get('/recetas/combos', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'listarCombos']);
    Route::post('/recetas/combos', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarCombo']);
    Route::put('/recetas/combos/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'guardarCombo']);
    Route::delete('/recetas/combos/{id}', [\App\Infrastructure\Http\Controllers\Api\RecetaController::class, 'eliminarCombo']);

    // Traspasos Inter-Sucursales - User Story 4 & User Story 21
    Route::post('/traspasos/enviar', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'enviar']);
    Route::post('/traspasos/{id}/recibir', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'recibir']);
    Route::get('/traspasos/pendientes', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'listarPendientes']);
    Route::get('/traspasos/stock-disponible', [\App\Infrastructure\Http\Controllers\Api\TraspasoController::class, 'stockDisponible']);

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

    // Declaración Directa de Bajas y Roturas en Barra - User Story 10 & 14
    Route::post('/inventario/bajas', [\App\Infrastructure\Http\Controllers\Api\TransformacionController::class, 'registrarBaja']);
    Route::get('/motivos-baja', [\App\Infrastructure\Http\Controllers\Api\MotivoBajaController::class, 'index']);
    Route::get('/motivos-baja/admin', [\App\Infrastructure\Http\Controllers\Api\MotivoBajaController::class, 'indexAdmin']);
    Route::post('/motivos-baja', [\App\Infrastructure\Http\Controllers\Api\MotivoBajaController::class, 'store']);
    Route::put('/motivos-baja/{id}', [\App\Infrastructure\Http\Controllers\Api\MotivoBajaController::class, 'update']);
    Route::delete('/motivos-baja/{id}', [\App\Infrastructure\Http\Controllers\Api\MotivoBajaController::class, 'destroy']);

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

    // Alertas de Fuga / Discrepancias entre Turnos y Dispositivos Maestros - User Story 15
    Route::get('/alertas/discrepancias', [\App\Infrastructure\Http\Controllers\Api\AlertaController::class, 'listarDiscrepancias']);
    Route::post('/alertas/{id}/resolver', [\App\Infrastructure\Http\Controllers\Api\AlertaController::class, 'resolverDiscrepancia']);
    Route::post('/dispositivos/registrar-maestro', [\App\Infrastructure\Http\Controllers\Api\AlertaController::class, 'registrarDispositivoMaestro']);
    Route::post('/dispositivos/desvincular', [\App\Infrastructure\Http\Controllers\Api\AlertaController::class, 'desvincularDispositivo']);
    Route::get('/dispositivos/maestros', [\App\Infrastructure\Http\Controllers\Api\AlertaController::class, 'listarDispositivosMaestros']);

    // Gestión de Proveedores Comerciales - User Story 19
    Route::get('/proveedores', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'index']);
    Route::post('/proveedores', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'store']);
    Route::get('/proveedores/{id}', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'show']);
    Route::put('/proveedores/{id}', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'update']);
    Route::delete('/proveedores/{id}', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'destroy']);
    Route::patch('/proveedores/{id}/toggle-activo', [\App\Infrastructure\Http\Controllers\Api\ProveedorController::class, 'toggleActivo']);

    // Monitoreo Operativo e Informe de Sucursal en Vivo/Histórico - User Story 20
    Route::get('/auditoria/sucursal/{sucursal_id}/informe-turno', [\App\Infrastructure\Http\Controllers\Api\AuditoriaController::class, 'informeTurnoSucursal']);
});


