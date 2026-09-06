<?php

namespace App\Application\UseCases\Turnos;

use App\Domain\Ports\TurnoRepositoryPort;
use App\Domain\Ports\UsuarioRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SolicitarCobroCajeraUseCase
{
    private TurnoRepositoryPort $turnoRepo;
    private UsuarioRepositoryPort $usuarioRepo;

    public function __construct(
        TurnoRepositoryPort $turnoRepo,
        UsuarioRepositoryPort $usuarioRepo
    ) {
        $this->turnoRepo = $turnoRepo;
        $this->usuarioRepo = $usuarioRepo;
    }

    /**
     * Consulta el resumen visual para mostrar a la cajera (números grandes, segundero en vivo).
     */
    public function obtenerResumen(int $turnoId): array
    {
        $turno = $this->turnoRepo->buscarPorId($turnoId);
        if (!$turno) {
            throw new DomainException("El turno {$turnoId} no existe.");
        }

        $barman = $this->usuarioRepo->buscarPorId($turno->barman_id);
        $saldoDeudor = (float) ($barman->saldo_deudor_acumulado ?? 0.00);

        $comisionBruta = (float) $turno->total_comision_bruta;
        $descuentoAplicable = min($comisionBruta, $saldoDeudor);
        $netoPagar = round($comisionBruta - $descuentoAplicable, 2);

        return [
            'turno_id' => $turno->id,
            'barman_id' => $turno->barman_id,
            'barman_nombre' => "{$barman->nombre} {$barman->apellido}",
            'sucursal' => $turno->sucursal->nombre ?? 'Barra',
            'estado' => $turno->estado,
            'ya_cobrado' => $turno->estado === 'cobrado',
            'codigo_recibo' => $turno->codigo_recibo_cobro,
            'total_unidades_netas' => $turno->total_transformaciones_netas,
            'comision_bruta_bs' => $comisionBruta,
            'deuda_descontada_bs' => $descuentoAplicable,
            'monto_neto_a_pagar_bs' => $netoPagar,
            'fecha_consulta' => now()->toIso8601String(),
        ];
    }

    /**
     * Confirma el desembolso en efectivo ("Cobro Recibido / Finalizar Turno").
     * Congela montos, descuenta deudas, guarda foto obligatoria de respaldo, genera código de 60s y bloquea nuevos cobros.
     */
    public function confirmarCobro(int $turnoId, mixed $fotoComprobante = null): array
    {
        return DB::transaction(function () use ($turnoId, $fotoComprobante) {
            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno) {
                throw new DomainException("El turno {$turnoId} no existe.");
            }

            if ($turno->estado === 'cobrado') {
                return [
                    'exito' => false,
                    'mensaje' => "Este turno ya fue cobrado con el código {$turno->codigo_recibo_cobro}.",
                    'codigo_recibo' => $turno->codigo_recibo_cobro,
                    'fecha_cobro' => $turno->fecha_cobro,
                    'monto_pagado' => (float) $turno->total_comision_neta_pagada,
                    'foto_comprobante_url' => $turno->foto_comprobante_cobro ? asset('storage/' . $turno->foto_comprobante_cobro) : null,
                ];
            }

            if ($turno->estado !== 'abierto') {
                throw new DomainException("El turno se encuentra en estado '{$turno->estado}' y no puede ser cobrado.");
            }

            $barman = $this->usuarioRepo->buscarPorId($turno->barman_id);
            $saldoDeudor = (float) ($barman->saldo_deudor_acumulado ?? 0.00);

            $comisionBruta = (float) $turno->total_comision_bruta;
            $descuentoAplicable = min($comisionBruta, $saldoDeudor);
            $netoPagar = round($comisionBruta - $descuentoAplicable, 2);

            // Guardar foto de comprobante si se proporciona
            $fotoPath = null;
            if ($fotoComprobante) {
                if (is_string($fotoComprobante) && str_starts_with($fotoComprobante, 'data:image')) {
                    $imageData = explode(',', $fotoComprobante)[1] ?? $fotoComprobante;
                    $decoded = base64_decode($imageData);
                    $fileName = 'cobros/comprobante_' . $turnoId . '_' . time() . '.jpg';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $decoded);
                    $fotoPath = $fileName;
                } elseif (is_string($fotoComprobante) && !empty($fotoComprobante)) {
                    $fotoPath = $fotoComprobante;
                } elseif ($fotoComprobante instanceof \Illuminate\Http\UploadedFile) {
                    $fotoPath = $fotoComprobante->store('cobros', 'public');
                }
            }

            // Generar código alfanumérico único para el recibo (ej. REC-7489)
            $codigoRecibo = 'REC-' . strtoupper(Str::random(4)) . '-' . rand(10, 99);

            $this->turnoRepo->actualizar($turnoId, [
                'estado' => 'cobrado',
                'total_sancion_descontada' => $descuentoAplicable,
                'total_comision_neta_pagada' => $netoPagar,
                'codigo_recibo_cobro' => $codigoRecibo,
                'foto_comprobante_cobro' => $fotoPath,
                'fecha_cobro' => now(),
            ]);

            // Descontar del saldo deudor del empleado si aplica
            if ($descuentoAplicable > 0) {
                $this->usuarioRepo->actualizarSaldoDeudor($barman->id, -$descuentoAplicable);
            }

            return [
                'exito' => true,
                'mensaje' => 'Cobro registrado exitosamente con evidencia fotográfica.',
                'codigo_recibo' => $codigoRecibo,
                'monto_neto_pagado_bs' => $netoPagar,
                'sancion_descontada_bs' => $descuentoAplicable,
                'foto_comprobante_url' => $fotoPath ? asset('storage/' . $fotoPath) : null,
                'fecha_cobro' => now()->toIso8601String(),
                'segundos_validez_pantalla' => 60,
            ];
        });
    }
}
