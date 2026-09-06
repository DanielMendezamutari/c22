<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Ports\TurnoRepositoryPort;
use App\Domain\Ports\UsuarioRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class LoginPinUseCase
{
    private UsuarioRepositoryPort $usuarioRepo;
    private TurnoRepositoryPort $turnoRepo;

    public function __construct(
        UsuarioRepositoryPort $usuarioRepo,
        TurnoRepositoryPort $turnoRepo
    ) {
        $this->usuarioRepo = $usuarioRepo;
        $this->turnoRepo = $turnoRepo;
    }

    public function ejecutar(?int $sucursalId, ?int $usuarioId, string $pin): array
    {
        \Illuminate\Support\Facades\Log::info('LoginPinUseCase::ejecutar', [
            'pin' => $pin,
            'sucursal_id' => $sucursalId,
            'usuario_id' => $usuarioId,
        ]);

        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            throw new InvalidArgumentException("El PIN debe ser exactamente de 4 dígitos numéricos.");
        }

        $usuario = null;

        // Si se envía usuarioId explícito, buscar directo
        if ($usuarioId) {
            $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
            if (!$usuario || !$usuario->activo || !Hash::check($pin, $usuario->pin_hash)) {
                throw new DomainException("PIN incorrecto o usuario inactivo.");
            }
        } else {
            // Autenticación implícita por PIN ciego: Buscar entre los usuarios activos
            $usuariosActivos = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::where('activo', true)->get();
            foreach ($usuariosActivos as $u) {
                if (Hash::check($pin, $u->pin_hash)) {
                    $usuario = $u;
                    break;
                }
            }

            if (!$usuario) {
                throw new DomainException("PIN no reconocido o usuario inactivo.");
            }
        }

        // Si es administrador, tiene alcance global y no requiere sucursal fija
        if ($usuario->rol === 'admin') {
            $token = $usuario->createToken('pin-auth-token')->plainTextToken;
            return [
                'token' => $token,
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'rol' => $usuario->rol,
                    'modalidad_cobro' => $usuario->modalidad_cobro,
                    'saldo_deudor' => (float) $usuario->saldo_deudor_acumulado,
                ],
                'sucursal' => [
                    'id' => 0,
                    'nombre' => 'Todas las Sucursales (Global)',
                ],
                'turno_activo' => null,
            ];
        }

        // Para barman o garzón, validar que se haya proporcionado una sucursal
        if (!$sucursalId || $sucursalId <= 0) {
            throw new DomainException("Por favor selecciona la sucursal de tu turno.");
        }

        // Si rota semanalmente a una nueva sucursal, actualizar sucursal actual
        if ($usuario->rol === 'barman' && $usuario->sucursal_actual_id !== $sucursalId) {
            $this->usuarioRepo->actualizarSucursalRotacion($usuario->id, $sucursalId);
            $usuario->sucursal_actual_id = $sucursalId;
        }

        // Buscar turno activo del barman en la sucursal
        $turnoActivo = $this->turnoRepo->buscarTurnoActivoPorBarman($usuario->id, $sucursalId);

        // Generar token Sanctum
        $token = $usuario->createToken('pin-auth-token')->plainTextToken;

        return [
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'rol' => $usuario->rol,
                'modalidad_cobro' => $usuario->modalidad_cobro,
                'saldo_deudor' => (float) $usuario->saldo_deudor_acumulado,
            ],
            'sucursal' => [
                'id' => $sucursalId,
                'nombre' => $usuario->sucursalActual->nombre ?? 'Sucursal Activa',
            ],
            'turno_activo' => $turnoActivo ? [
                'id' => $turnoActivo->id,
                'tipo_turno' => $turnoActivo->tipo_turno,
                'estado' => $turnoActivo->estado,
                'fecha_apertura' => $turnoActivo->fecha_apertura->toIso8601String(),
            ] : null,
        ];
    }
}
