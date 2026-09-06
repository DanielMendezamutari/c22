<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaVenta;
use App\Infrastructure\Persistence\Eloquent\Models\SancionInventario;
use App\Infrastructure\Persistence\Eloquent\Models\LiquidacionSemanal;

interface AuditoriaRepositoryPort
{
    public function registrarAuditoria(array $datos): AuditoriaVenta;
    public function buscarPorTurnoId(int $turnoId): ?AuditoriaVenta;
    public function registrarSancion(array $datos): SancionInventario;
    public function obtenerSancionesPendientes(int $usuarioId): array;
    public function marcarSancionComoDescontada(int $sancionId, float $monto, string $modo): bool;
    public function generarLiquidacionSemanal(array $datos): LiquidacionSemanal;
    public function obtenerLiquidacionSemanal(string $semana, int $sucursalId): array;
    public function obtenerReporteRatios(int $sucursalId, ?int $productoDestinoId = null): array;
}
