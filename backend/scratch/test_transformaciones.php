<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Application\UseCases\Transformacion\RegistrarTransformacionUseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$uc = app(RegistrarTransformacionUseCase::class);

// Turno abierto check
$turno = DB::table('turnos')->where('estado', 'abierto')->first();
if (!$turno) {
    echo "No hay turno abierto para probar. Abriendo uno...\n";
    $turnoId = DB::table('turnos')->insertGetId([
        'sucursal_id' => 1,
        'barman_id' => 1,
        'tipo_turno' => 'noche',
        'estado' => 'abierto',
        'fecha_apertura' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
} else {
    $turnoId = $turno->id;
}

echo "Usando Turno ID: $turnoId\n";

// Test 1: Insumo Alternativo (Paceña id 6)
$data1 = [
    'uuid_local' => (string) Str::uuid(),
    'turno_id' => $turnoId,
    'receta_id' => 1,
    'insumo_origen_id' => 6, // Paceña
    'cantidad_insumo' => 24,
    'cantidad_producida' => 20,
    'cantidad_roturas' => 0,
    'observaciones' => 'Prueba con cerveza Paceña alternativa'
];

try {
    $res1 = $uc->ejecutar($data1);
    echo "TEST 1 (Insumo Alternativo) EXITO: " . json_encode($res1) . "\n";
} catch (\Throwable $e) {
    echo "TEST 1 (Insumo Alternativo) ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Insumos Compuestos (Blackstone + Chancellor -> Red Label)
$bstone = DB::table('productos')->where('nombre', 'like', '%Blackstone%')->value('id');
$chanc = DB::table('productos')->where('nombre', 'like', '%Chancellor%')->value('id');

$data2 = [
    'uuid_local' => (string) Str::uuid(),
    'turno_id' => $turnoId,
    'receta_id' => 2,
    'insumos_origen' => [
        ['insumo_id' => $bstone, 'cantidad' => 0.6],
        ['insumo_id' => $chanc, 'cantidad' => 0.4],
    ],
    'cantidad_insumo' => 1.0,
    'cantidad_producida' => 1,
    'cantidad_roturas' => 0,
    'observaciones' => 'Prueba compuesto Blackstone + Chancellor -> Red Label'
];

try {
    $res2 = $uc->ejecutar($data2);
    echo "TEST 2 (Compuesto Blackstone + Chancellor) EXITO: " . json_encode($res2) . "\n";
} catch (\Throwable $e) {
    echo "TEST 2 (Compuesto Blackstone + Chancellor) ERROR: " . $e->getMessage() . "\n";
}
