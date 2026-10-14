<?php

namespace App\Jobs;

use App\Application\UseCases\Auditoria\AuditoriaCruceCanalesUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\GastoCajaChica;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Services\GeminiVisionAuditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarMensajeWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $inboundId;

    public function __construct(int $inboundId)
    {
        $this->inboundId = $inboundId;
    }

    public function handle(GeminiVisionAuditorService $visionAuditor, AuditoriaCruceCanalesUseCase $cruceCanalesUseCase): void
    {
        $inbound = WhatsAppMensajeInbound::find($this->inboundId);
        if (!$inbound) {
            Log::warning("ProcesarMensajeWhatsAppJob: Inbound ID {$this->inboundId} no encontrado.");
            return;
        }

        if (empty($inbound->media_path)) {
            $inbound->estado = 'procesado';
            $inbound->save();
            return;
        }

        try {
            $contextoPartes = [];
            if (!empty($inbound->sender_name)) $contextoPartes[] = "Remitente: {$inbound->sender_name}";
            if (!empty($inbound->sender_phone)) $contextoPartes[] = "Tel: +{$inbound->sender_phone}";
            if (!empty($inbound->raw_text)) $contextoPartes[] = "Texto adjunto: {$inbound->raw_text}";
            $contextoTexto = implode(' | ', $contextoPartes);

            $resultado = $visionAuditor->auditarYClasificar($inbound->media_path, 'image/jpeg', $contextoTexto);

            $inbound->clasificacion_ia = in_array($resultado['clasificacion'], [
                'planilla_caja', 'voucher_deposito', 'recibo_gasto', 'ticket_pos', 'otro'
            ]) ? $resultado['clasificacion'] : 'otro';
            $inbound->score_confianza = $resultado['score_confianza'];
            $inbound->metadata_ia = $resultado['datos'];

            // Inferencia y auto-detección inteligente de la sucursal por IA
            if (!$inbound->sucursal_id) {
                $autoSucursalId = self::inferirSucursalId($resultado, $inbound);
                if ($autoSucursalId) {
                    $inbound->sucursal_id = $autoSucursalId;
                    Log::info("ProcesarMensajeWhatsAppJob: Sucursal auto-detectada por IA: ID {$autoSucursalId}");
                }
            }

            $datos = $resultado['datos'] ?? [];

            // Detectar si la imagen representa el evento de cierre de turno (Planilla de Rendición o Corte Z del POS)
            $esCierreTurno = ($inbound->clasificacion_ia === 'planilla_caja') ||
                             ($inbound->clasificacion_ia === 'ticket_pos' && !empty($datos['es_cierre_turno']));

            if ($esCierreTurno && $inbound->sucursal_id) {
                $tipoTurno = strtolower($datos['turno'] ?? (now()->hour >= 6 && now()->hour < 18 ? 'dia' : 'noche'));
                $fechaOperativa = $datos['fecha'] ?? now()->toDateString();

                // 1. Buscar turno abierto en la sucursal para cerrarlo formalmente
                $turno = Turno::where('sucursal_id', $inbound->sucursal_id)
                    ->where('estado', 'abierto')
                    ->latest('id')
                    ->first();

                if ($turno) {
                    $turno->estado = 'cerrado';
                    $turno->fecha_cierre = now();
                    $turno->save();
                } else {
                    // 2. Si no hay turno abierto en el sistema (offline/RestoTech sin API), verificar si ya se creó uno cerrado hace menos de 2 horas
                    $reciente = Turno::where('sucursal_id', $inbound->sucursal_id)
                        ->where('estado', 'cerrado')
                        ->where('tipo_turno', $tipoTurno)
                        ->where('fecha_cierre', '>=', now()->subHours(2))
                        ->latest('id')
                        ->first();

                    if ($reciente) {
                        $turno = $reciente;
                    } else {
                        // 3. Crear turno autónomo para este cierre de turno con barman_id válido
                        $user = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::where('sucursal_actual_id', $inbound->sucursal_id)->first()
                            ?? \App\Infrastructure\Persistence\Eloquent\Models\Usuario::first();
                        if (!$user) {
                            $user = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::create([
                                'nombre' => 'Sistema',
                                'apellido' => 'Autónomo',
                                'rol' => 'barman',
                                'pin_hash' => bcrypt('0000'),
                                'sucursal_actual_id' => $inbound->sucursal_id,
                                'activo' => true,
                            ]);
                        }

                        $turno = Turno::create([
                            'sucursal_id' => $inbound->sucursal_id,
                            'barman_id' => $user->id,
                            'tipo_turno' => $tipoTurno,
                            'estado' => 'cerrado',
                            'fecha_apertura' => \Carbon\Carbon::parse($fechaOperativa)->setTime($tipoTurno === 'dia' ? 8 : 20, 0),
                            'fecha_cierre' => now(),
                        ]);
                    }
                }

                $inbound->turno_id = $turno->id;
                $inbound->save();
            } else if ($inbound->sucursal_id && !$inbound->turno_id) {
                // Para gastos, vouchers o tickets previos al cierre, asociar al turno abierto o al más reciente (últimas 6h)
                $turno = Turno::where('sucursal_id', $inbound->sucursal_id)
                    ->where(function ($q) {
                        $q->where('estado', 'abierto')
                          ->orWhere('fecha_cierre', '>=', now()->subHours(6));
                    })
                    ->latest('id')
                    ->first();
                $inbound->turno_id = $turno?->id;
                $inbound->save();
            }

            // Si es planilla de caja, persistir o actualizar en planillas_caja para alimentar la Conciliación Triangulada
            if ($inbound->clasificacion_ia === 'planilla_caja' && $inbound->turno_id) {
                PlanillaCaja::updateOrCreate(
                    ['turno_id' => $inbound->turno_id],
                    [
                        'sucursal_id' => $inbound->sucursal_id,
                        'fecha_operativa' => $datos['fecha'] ?? now()->toDateString(),
                        'foto_url' => $inbound->media_path,
                        'total_ventas_declaradas_bs' => $datos['total_ventas_declaradas_bs'] ?? 0.00,
                        'total_gastos_declarados_bs' => $datos['total_gastos_declarados_bs'] ?? 0.00,
                        'monto_sobre_efectivo_bs' => $datos['monto_sobre_efectivo_bs'] ?? 0.00,
                        'cajero_nombre' => $datos['cajero_nombre'] ?? $inbound->sender_name,
                        'datos_ocr_json' => $datos,
                        'estado_ocr' => 'procesado',
                        'observaciones' => $datos['observaciones'] ?? 'Procesado automáticamente vía WhatsApp Bot y Gemini Vision',
                    ]
                );

                // Auto-despacho autónomo del informe de auditoría a WhatsApp (Control de Recaudo)
                try {
                    $despachador = app(\App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase::class);
                    $despachador->ejecutar($inbound->turno_id, true);
                    Log::info("Informe de auditoría y cierre despachado automáticamente para Turno {$inbound->turno_id} a WhatsApp.");
                } catch (\Throwable $e) {
                    Log::warning("No se pudo autodespachar informe de auditoría para Turno {$inbound->turno_id}: " . $e->getMessage());
                }
            }

            // Si es ticket_pos y hay turno, actualizar o enriquecer los datos OCR de la planilla con las ventas del POS
            if ($inbound->clasificacion_ia === 'ticket_pos' && $inbound->turno_id) {
                $planilla = PlanillaCaja::firstOrNew(['turno_id' => $inbound->turno_id]);
                $datosActuales = $planilla->datos_ocr_json ?? [];
                $datosActuales['ventas_pos'] = $datos['total_ticket_bs'] ?? 0.00;
                $datosActuales['ventas_qr_bs'] = $datos['qr_pos_bs'] ?? 0.00;
                $datosActuales['ticket_pos_foto_url'] = $inbound->media_path;
                
                $planilla->sucursal_id = $inbound->sucursal_id;
                $planilla->datos_ocr_json = $datosActuales;
                if (!$planilla->foto_url) {
                    $planilla->foto_url = $inbound->media_path;
                }
                $planilla->save();

                // Si ya tenemos la planilla física declarada, re-despachar el informe con la conciliación real contra POS
                if (!empty($datosActuales['total_ventas_declaradas_bs']) || !empty($datosActuales['monto_sobre_efectivo_bs'])) {
                    try {
                        $despachador = app(\App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase::class);
                        $despachador->ejecutar($inbound->turno_id, true);
                        Log::info("Informe de auditoría actualizado y re-despachado con datos reales del POS para Turno {$inbound->turno_id}.");
                    } catch (\Throwable $e) {
                        Log::warning("No se pudo re-despachar informe con datos POS: " . $e->getMessage());
                    }
                }
            }

            // Si es recibo_gasto o voucher con motivo de gasto, persistir en gastos_caja_chica
            if ($inbound->clasificacion_ia === 'recibo_gasto' && $inbound->sucursal_id) {
                try {
                    $montoGasto = (float) ($datos['monto_bs'] ?? $datos['monto_total_bs'] ?? 0.00);
                    $conceptoGasto = $datos['concepto'] ?? ($inbound->raw_text ?: 'Gasto de Caja Chica vía WhatsApp');
                    $categoriaGasto = $datos['categoria'] ?? 'otros';

                    GastoCajaChica::create([
                        'sucursal_id' => $inbound->sucursal_id,
                        'turno_id' => $inbound->turno_id,
                        'fecha' => now()->toDateString(),
                        'concepto' => $conceptoGasto,
                        'categoria' => $categoriaGasto,
                        'monto_bs' => $montoGasto,
                        'foto_comprobante_url' => $inbound->media_path,
                        'estado_comprobante' => 'aprobado_con_foto',
                        'emparejado_ocr' => true,
                        'observaciones' => "Registrado vía WhatsApp Bot (" . ($inbound->sender_name ?: $inbound->sender_phone) . ")",
                    ]);
                    Log::info("ProcesarMensajeWhatsAppJob: Gasto de caja chica de {$montoGasto} Bs registrado para Sucursal {$inbound->sucursal_id}.");
                } catch (\Throwable $e) {
                    Log::warning("ProcesarMensajeWhatsAppJob: Error persistiendo GastoCajaChica: " . $e->getMessage());
                }
            }

            // Calcular SHA-256 de la imagen para deduplicación inter-canales
            $hashImagen = null;
            $fullPath = public_path($inbound->media_path);
            if (file_exists($fullPath)) {
                $hashImagen = hash_file('sha256', $fullPath);
            }

            // Ejecutar Auditoría Cruzada de Veracidad Inter-Canales si tenemos sucursal
            if ($inbound->sucursal_id) {
                $tipoEvento = match ($inbound->clasificacion_ia) {
                    'planilla_caja', 'ticket_pos' => 'planilla_cierre',
                    'recibo_gasto' => 'gasto_caja_chica',
                    'voucher_deposito' => 'voucher_banco',
                    default => 'otro',
                };

                if ($tipoEvento !== 'otro') {
                    $montoDeclarado = (float) match ($inbound->clasificacion_ia) {
                        'planilla_caja' => $datos['total_ventas_declaradas_bs'] ?? 0.00,
                        'ticket_pos' => $datos['total_ticket_bs'] ?? 0.00,
                        'recibo_gasto' => $datos['monto_total_bs'] ?? 0.00,
                        'voucher_deposito' => $datos['monto_depositado_bs'] ?? 0.00,
                        default => 0.00,
                    };

                    $cruceCanalesUseCase->ejecutar(
                        $inbound->id,
                        $inbound->sucursal_id,
                        $tipoEvento,
                        $montoDeclarado,
                        $hashImagen,
                        $inbound->turno_id
                    );
                }
            }

            $inbound->estado = $inbound->sucursal_id ? 'procesado' : 'requiere_confirmacion';
            $inbound->save();

            Log::info("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id} clasificado como {$inbound->clasificacion_ia} (confianza: {$inbound->score_confianza})");
        } catch (\Throwable $e) {
            Log::error("ProcesarMensajeWhatsAppJob Error: " . $e->getMessage());
            $inbound->estado = 'error';
            $inbound->save();
        }
    }

    /**
     * Infiere y auto-detecta la sucursal de Casa22 examinando pistas de IA, encabezados y cajeras.
     */
    public static function inferirSucursalId(array $resultado, WhatsAppMensajeInbound $inbound): ?int
    {
        $textos = [
            $resultado['sucursal_sugerida'] ?? '',
            $resultado['datos']['servidor_cajero'] ?? '',
            $resultado['datos']['cajero_nombre'] ?? '',
            $resultado['datos']['observaciones'] ?? '',
            $inbound->raw_text ?? '',
            $inbound->sender_name ?? '',
            json_encode($resultado['datos'] ?? []),
        ];

        $todo = strtolower(implode(' ', $textos));
        $todoNorm = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $todo);

        // 1. Casa Belén (C22) -> ID 1
        if (
            str_contains($todoNorm, 'belen') ||
            str_contains($todoNorm, 'c22') ||
            str_contains($todoNorm, 'casa22') ||
            str_contains($todoNorm, 'casa 22') ||
            str_contains($todoNorm, 'fanny') ||
            str_contains($todoNorm, 'cuevas')
        ) {
            return 1;
        }

        // 2. Casa Corona -> ID 2
        if (str_contains($todoNorm, 'corona') || str_contains($todoNorm, 'coron')) {
            return 2;
        }

        // 3. Madan / Madam -> ID 3
        if (str_contains($todoNorm, 'madan') || str_contains($todoNorm, 'madam')) {
            return 3;
        }

        // 4. Casa 22 VIP -> ID 5 (o búsqueda por código C22VIP)
        if (str_contains($todoNorm, 'vip') || str_contains($todoNorm, 'c22vip') || str_contains($todoNorm, 'gareca')) {
            $sucVip = Sucursal::where('codigo', 'C22VIP')->orWhere('nombre', 'like', '%vip%')->first();
            return $sucVip ? $sucVip->id : 5;
        }

        // 5. Cuarta Casa / Casa Nueva / Joker -> ID 8 (o búsqueda por código CASA4)
        if (
            str_contains($todoNorm, 'cuarta') ||
            str_contains($todoNorm, 'casa 4') ||
            str_contains($todoNorm, 'casa4') ||
            str_contains($todoNorm, 'nueva') ||
            str_contains($todoNorm, 'joker')
        ) {
            $suc4 = Sucursal::where('codigo', 'CASA4')->orWhere('nombre', 'like', '%cuarta%')->orWhere('nombre', 'like', '%nueva%')->first();
            return $suc4 ? $suc4->id : 8;
        }

        return null;
    }
}
