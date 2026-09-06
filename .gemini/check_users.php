<?php
require 'c:/xampp/htdocs/next22/backend/vendor/autoload.php';
$app = require_once 'c:/xampp/htdocs/next22/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$eloquentUsers = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::where('activo', true)->get();
echo "Eloquent count: " . $eloquentUsers->count() . "\n";
foreach ($eloquentUsers as $u) {
    echo "Eloquent: {$u->id} | {$u->nombre} | PIN 9999 match: " . (Illuminate\Support\Facades\Hash::check('9999', $u->pin_hash) ? 'YES' : 'NO') . "\n";
}
