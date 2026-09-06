<?php

namespace App\Application\UseCases\Inventario;

use App\Domain\Ports\MovimientoRepositoryPort;
use App\Domain\Ports\TurnoRepositoryPort;
use App\Infrastructure\Services\StorageService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarIngresoUseCase
{
    private TurnoRepositoryPort $turnoRepo;
    private MovimientoRepositoryPort $movimientoRepo;
    private StorageService $storageService;

    public function __construct(
        TurnoRepositoryPort $turnoRepo,
        MovimientoRepositoryPort $movimientoRepo,
        StorageService $storageService
    ) {
        $this->turnoRepo = $turnoRepo;
        $this->movimientoRepo = $movimientoRepo;
        $this->storageService = $storageService;
    }

    public function ejecutar(array $comando, UploadedFile|string|null $archivoFoto = null): array
    {
        $uuidLocal = $comando['uuid_local'] ?? null;
        if (!$uuidLocal) {
            throw new InvalidArgumentException("El UUID local es obligatorio.");
        }

        if ($this->movimientoRepo->existeUuid($uuidLocal)) {
            return [
                'idempotente' => true,
                'mensaje' => 'El ingreso ya había sido registrado.',
                'uuid_local' => $uuidLocal,
            ];
        }

        // Regla Constitucional de Evidencia: La foto es OBLIGATORIA para recepciones externas
        $fotoPath = $comando['foto_path'] ?? null;
        if ($archivoFoto instanceof UploadedFile) {
            $fotoPath = $this->storageService->guardarFoto($archivoFoto, 'ingresos');
        } elseif (is_string($archivoFoto) && !empty($archivoFoto)) {
            $fotoPath = $this->storageService->guardarFotoBase64($archivoFoto, 'ingresos');
        }

        if (empty($fotoPath)) {
            throw new DomainException("La evidencia fotográfica de la nota de entrega es obligatoria para registrar el ingreso.");
        }

        return DB::transaction(function () use ($comando, $uuidLocal, $fotoPath) {
            $turnoId = (int) $comando['turno_id'];
            $productoId = (int) $comando['producto_id'];
            $cantidad = (float) $comando['cantidad'];
            $observaciones = $comando['observaciones'] ?? 'Recepción de mercadería externa';

            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno || $turno->estado !== 'abierto') {
                throw new DomainException("El turno {$turnoId} no se encuentra abierto para recibir mercadería.");
            }

            $mov = $this->movimientoRepo->registrarMovimiento([
                'uuid_local' => $uuidLocal,
                'turno_id' => $turnoId,
                'sucursal_id' => $turno->sucursal_id,
                'producto_id' => $productoId,
                'tipo_movimiento' => 'ingreso',
                'cantidad' => $cantidad,
                'foto_path' => $fotoPath,
                'observaciones' => $observaciones,
                'fecha_movimiento' => now(),
            ]);

            return [
                'idempotente' => false,
                'movimiento_id' => $mov->id,
                'turno_id' => $turnoId,
                'producto_id' => $productoId,
                'cantidad_ingresada' => $cantidad,
                'foto_path' => $fotoPath,
                'mensaje' => 'Ingreso registrado con evidencia fotográfica exitosamente.',
            ];
        });
    }
}
