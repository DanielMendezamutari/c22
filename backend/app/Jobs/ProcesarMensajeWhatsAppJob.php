<?php

namespace App\Jobs;

use App\Application\UseCases\Auditoria\AuditoriaCruceCanalesUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\GastoCajaChica;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeOutbound;
use App\Services\GeminiVisionAuditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

        $lockCierre = null;

        // La misma foto reenviada a otro grupo (o dos veces) no crea otro cierre ni gasta otra lectura de IA
        $original = self::registrarHuella($inbound);
        if ($original) {
            $distintaCasa = $original->sucursal_id && $inbound->sucursal_id && $original->sucursal_id !== $inbound->sucursal_id;
            Log::warning("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id} es la misma imagen que el {$original->id}"
                . ($distintaCasa ? " pero llegó por el grupo de otra casa ({$inbound->sucursal_id} vs {$original->sucursal_id})" : '') . '; no se procesa.');
            $inbound->estado = 'duplicado';
            $inbound->metadata_ia = ['duplicado_de' => $original->id, 'grupo_de_otra_casa' => $distintaCasa];
            $inbound->save();

            return;
        }

        try {
            $contextoPartes = [];
            if (!empty($inbound->sender_name)) $contextoPartes[] = "Remitente: {$inbound->sender_name}";
            if (!empty($inbound->sender_phone)) $contextoPartes[] = "Tel: +{$inbound->sender_phone}";
            if (!empty($inbound->raw_text)) $contextoPartes[] = "Texto adjunto: {$inbound->raw_text}";
            $contextoTexto = implode(' | ', $contextoPartes);

            // El tipo real del archivo: un PDF enviado como image/jpeg lo rechaza Gemini con 400 "Unable to process input image"
            $mimetype = match (strtolower(pathinfo((string) $inbound->media_path, PATHINFO_EXTENSION))) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
            $resultado = $visionAuditor->auditarYClasificar($inbound->media_path, $mimetype, $contextoTexto);

            // Sin IA no hay datos: el mensaje espera revisión humana sin efectos contables (spec 004 FR-006)
            if (($resultado['ia_disponible'] ?? true) === false) {
                $inbound->clasificacion_ia = 'desconocido';
                $inbound->score_confianza = 0;
                $inbound->metadata_ia = $resultado['datos'] ?? null;
                $inbound->estado = 'requiere_revision';
                $inbound->save();
                Log::warning("ProcesarMensajeWhatsAppJob: IA no disponible para Inbound {$inbound->id}; queda en revisión.");
                return;
            }

            $inbound->clasificacion_ia = in_array($resultado['clasificacion'], [
                'planilla_caja', 'voucher_deposito', 'recibo_gasto', 'ticket_pos', 'cierre_barra', 'otro'
            ]) ? $resultado['clasificacion'] : 'otro';
            $inbound->score_confianza = $resultado['score_confianza'];
            $inbound->metadata_ia = $resultado['datos'];
            $estadoFinal = null;

            // Casa por evidencia del documento. Si contradice al grupo (la encargada lo mandó al grupo de otra
            // casa), manda el documento: el encabezado del POS dice de qué casa es.
            $casaDocumento = self::inferirSucursalId($resultado, $inbound, soloEncabezado: (bool) $inbound->sucursal_id);
            if ($casaDocumento && $casaDocumento !== $inbound->sucursal_id) {
                if ($inbound->sucursal_id) {
                    Log::warning("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id} llegó por el grupo de la sucursal {$inbound->sucursal_id} pero el documento es de la sucursal {$casaDocumento}; se reasigna.");
                    $inbound->turno_id = null;
                }
                $inbound->sucursal_id = $casaDocumento;
            }

            $datos = $resultado['datos'] ?? [];

            // Dos planillas de la misma casa procesándose a la vez no deben crear turnos ni informes duplicados
            if (in_array($inbound->clasificacion_ia, ['planilla_caja', 'ticket_pos'], true) && $inbound->sucursal_id) {
                $lockCierre = Cache::lock("cierre-planilla-sucursal-{$inbound->sucursal_id}", 120);
                $lockCierre->block(60);
            }

            // Detectar si la imagen representa el evento de cierre de turno (Planilla de Rendición o Corte Z del POS)
            $esCierreTurno = ($inbound->clasificacion_ia === 'planilla_caja') ||
                             ($inbound->clasificacion_ia === 'ticket_pos' && !empty($datos['es_cierre_turno']));

            if ($esCierreTurno && $inbound->sucursal_id) {
                // La IA responde "Día"/"Noche"; la BD usa 'dia'/'noche'
                $turnoIa = self::normalizar((string) ($datos['turno'] ?? ''));
                $tipoTurno = $turnoIa !== ''
                    ? (str_contains($turnoIa, 'noche') ? 'noche' : 'dia')
                    : (now()->hour >= 6 && now()->hour < 18 ? 'dia' : 'noche');
                [$fechaOperativa, $fechaCorregida] = self::fechaOperativa($datos['fecha'] ?? null, $tipoTurno, $inbound->created_at ?? now());
                if ($fechaCorregida) {
                    Log::warning("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id}: la IA leyó la fecha '" . ($datos['fecha'] ?? 'vacía') . "', se usa {$fechaOperativa} según la hora de envío.");
                    $datos['fecha_leida_ia'] = $datos['fecha'] ?? null;
                    $datos['fecha'] = $fechaOperativa;
                    $inbound->metadata_ia = $datos;
                }

                // 1. Buscar turno abierto en la sucursal para cerrarlo formalmente
                // 0. Mismo cierre ya registrado (misma fecha y tipo de turno): reutilizar su turno.
                // Evita que una foto tardía cree otro turno o cierre el turno siguiente (spec 004 FR-004).
                $turnoMismoCierre = $fechaOperativa
                    ? PlanillaCaja::where('sucursal_id', $inbound->sucursal_id)
                        ->whereDate('fecha_operativa', $fechaOperativa)
                        ->whereIn('turno_id', Turno::where('sucursal_id', $inbound->sucursal_id)->where('tipo_turno', $tipoTurno)->select('id'))
                        ->oldest('id')
                        ->value('turno_id')
                    : null;

                $turno = $turnoMismoCierre
                    ? null
                    : Turno::where('sucursal_id', $inbound->sucursal_id)
                        ->where('estado', 'abierto')
                        ->where('tipo_turno', $tipoTurno)
                        // Solo un turno abierto de este mismo cierre: el #2 de Corona seguía "abierto" desde septiembre
                        ->where('fecha_apertura', '>=', now()->subHours(20))
                        ->latest('id')
                        ->first();

                if ($turnoMismoCierre) {
                    $turno = Turno::find($turnoMismoCierre);
                } elseif ($turno) {
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
                        // 3. Crear turno autónomo para este cierre con un responsable de ESTA sucursal
                        $user = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::where('sucursal_actual_id', $inbound->sucursal_id)->first();
                        if (!$user) {
                            // Usuario técnico inactivo y sin PIN utilizable: solo satisface la FK del turno
                            $user = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::create([
                                'nombre' => 'Sistema',
                                'apellido' => 'Autónomo',
                                'rol' => 'barman',
                                'pin_hash' => bcrypt(\Illuminate\Support\Str::random(40)),
                                'sucursal_actual_id' => $inbound->sucursal_id,
                                'activo' => false,
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
                // Una planilla de encargada ya aceptada para el turno no se sobrescribe (spec 004 FR-004)
                $yaTienePlanilla = WhatsAppMensajeInbound::where('turno_id', $inbound->turno_id)
                    ->where('clasificacion_ia', 'planilla_caja')
                    ->where('estado', 'procesado')
                    ->where('id', '!=', $inbound->id)
                    ->exists();

                if ($yaTienePlanilla) {
                    $estadoFinal = 'requiere_revision';
                    Log::info("ProcesarMensajeWhatsAppJob: Turno {$inbound->turno_id} ya tiene planilla; Inbound {$inbound->id} queda en revisión sin despacho.");
                } else {
                    // Conservar lo que haya aportado un ticket POS previo
                    $existente = PlanillaCaja::where('turno_id', $inbound->turno_id)->first();
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
                            'datos_ocr_json' => array_merge($existente?->datos_ocr_json ?? [], $datos),
                            'estado_ocr' => 'procesado',
                            'observaciones' => $datos['observaciones'] ?? 'Procesado automáticamente vía WhatsApp Bot y Gemini Vision',
                        ]
                    );

                    $corte = self::cortePosDelTurno($inbound->turno_id, $inbound->sucursal_id);
                    if ($corte) {
                        $planillaTurno = PlanillaCaja::where('turno_id', $inbound->turno_id)->first();
                        $planillaTurno->datos_ocr_json = array_merge(self::datosPos($corte->metadata_ia ?? [], $corte->media_path), $planillaTurno->datos_ocr_json ?? []);
                        $planillaTurno->save();
                    }

                    // Un solo informe automático por turno; los reenvíos son manuales desde la web
                    $yaDespachado = WhatsAppMensajeOutbound::where('turno_id', $inbound->turno_id)
                        ->where('tipo_mensaje', 'informe_cierre_ejecutivo')
                        ->where('estado_envio', 'enviado')
                        ->exists();
                    if (!config('services.whatsapp.informes_automaticos')) {
                        Log::info("Informe automático en pausa (INFORMES_AUTOMATICOS=false): Turno {$inbound->turno_id} queda para envío manual.");
                    } elseif (!$yaDespachado) {
                        try {
                            $despachador = app(\App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase::class);
                            $despachador->ejecutar($inbound->turno_id, false);
                            Log::info("Informe de auditoría y cierre despachado automáticamente para Turno {$inbound->turno_id} a WhatsApp.");
                        } catch (\Throwable $e) {
                            Log::warning("No se pudo autodespachar informe de auditoría para Turno {$inbound->turno_id}: " . $e->getMessage());
                        }
                    }
                }
            }

            // El ticket POS solo enriquece una planilla de encargada ya existente. Si llega antes, queda en el
            // mensaje y se incorpora cuando llegue la planilla (antes creaba una planilla sin fecha y fallaba).
            if ($inbound->clasificacion_ia === 'ticket_pos' && $inbound->turno_id && !empty($datos['es_cierre_turno'])) {
                $planilla = PlanillaCaja::where('turno_id', $inbound->turno_id)->first();
                if ($planilla) {
                    $planilla->datos_ocr_json = array_merge($planilla->datos_ocr_json ?? [], self::datosPos($datos, $inbound->media_path));
                    $planilla->save();
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

            $inbound->estado = $estadoFinal ?? ($inbound->sucursal_id ? 'procesado' : 'requiere_confirmacion');
            $inbound->save();

            Log::info("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id} clasificado como {$inbound->clasificacion_ia} (confianza: {$inbound->score_confianza})");
        } catch (\Throwable $e) {
            Log::error("ProcesarMensajeWhatsAppJob Error: " . $e->getMessage());
            $inbound->estado = 'error';
            $inbound->save();
        } finally {
            $lockCierre?->release();
        }
    }

    /**
     * Casa del documento según evidencia escrita en él (spec 005 FR-004), nunca por suposición de la IA:
     *  1. alias de la casa en el encabezado o textos del documento (sucursales.alias_documentos);
     *     gana el alias más largo y debe identificar a una sola casa;
     *  2. si no hay alias, la encargada registrada (rol cajera) cuyo nombre figura en la planilla.
     * Sin evidencia devuelve null y el mensaje queda para confirmación manual.
     */
    public static function inferirSucursalId(array $resultado, WhatsAppMensajeInbound $inbound, bool $soloEncabezado = false): ?int
    {
        // Las observaciones libres de la IA no cuentan ("20 whisky vip a la nueva casa" no hace VIP a la planilla)
        $datos = $resultado['datos'] ?? [];
        $encabezado = implode(' ', array_filter([$datos['servidor_cajero'] ?? null, $datos['encabezado'] ?? null], 'is_string'));

        // El encabezado del documento manda; el texto adjunto solo cuenta si el documento no dice de qué casa es
        $casa = self::casaPorAlias($encabezado);
        if ($casa !== false || $soloEncabezado) {
            return $casa ?: null;
        }
        $casa = self::casaPorAlias((string) $inbound->raw_text);
        if ($casa !== false) {
            return $casa;
        }

        $encargada = $datos['cajero_nombre'] ?? null;
        if ($encargada) {
            $candidatas = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::where('activo', true)
                ->where('rol', 'cajera')
                ->whereNotNull('sucursal_actual_id')
                ->get(['nombre', 'apellido', 'sucursal_actual_id'])
                ->filter(fn ($u) => \App\Domain\Cierre\NombrePersona::mismaPersona(trim($u->nombre . ' ' . $u->apellido), $encargada))
                ->pluck('sucursal_actual_id')
                ->unique();
            if ($candidatas->count() === 1) {
                return (int) $candidatas->first();
            }
        }

        return null;
    }

    /**
     * @return int|null|false id de la casa; null si dos casas empatan (decide una persona); false si no hay alias.
     */
    private static function casaPorAlias(string $textoOriginal): int|null|false
    {
        $texto = self::normalizar($textoOriginal);
        if ($texto === '') {
            return false;
        }

        $porCasa = [];
        foreach (Sucursal::where('activo', true)->whereNotNull('alias_documentos')->get(['id', 'alias_documentos']) as $casa) {
            foreach (explode(',', $casa->alias_documentos) as $alias) {
                $alias = self::normalizar($alias);
                if ($alias !== '' && preg_match('/\b' . preg_quote($alias, '/') . '\b/u', $texto)) {
                    $porCasa[$casa->id] = max($porCasa[$casa->id] ?? 0, mb_strlen($alias));
                }
            }
        }
        if ($porCasa === []) {
            return false;
        }
        arsort($porCasa);
        $largos = array_values($porCasa);

        return count($largos) === 1 || $largos[0] > $largos[1] ? (int) array_key_first($porCasa) : null;
    }

    /**
     * Reporte de cierre del POS (no comandas) del turno, o de la misma casa sin turno en las últimas 16 h
     * (tickets reasignados por encabezado). Si hay varios, el de mayor total cobrado. Lo vincula al turno.
     */
    private static function cortePosDelTurno(int $turnoId, int $sucursalId): ?WhatsAppMensajeInbound
    {
        $candidatos = WhatsAppMensajeInbound::where('clasificacion_ia', 'ticket_pos')
            ->where(fn ($q) => $q->where('turno_id', $turnoId)
                ->orWhere(fn ($q) => $q->whereNull('turno_id')->where('sucursal_id', $sucursalId)->where('created_at', '>=', now()->subHours(16))))
            ->get()
            ->filter(fn ($m) => !empty(($m->metadata_ia ?? [])['es_cierre_turno']))
            ->sortByDesc(fn ($m) => (float) (($m->metadata_ia ?? [])['total_cobros_bs'] ?? 0));

        $corte = $candidatos->first();
        if ($corte && !$corte->turno_id) {
            $corte->turno_id = $turnoId;
            $corte->save();
        }

        return $corte;
    }

    /**
     * Fecha operativa del cierre (día en que empezó el turno). La de la IA se acepta si está a 2 días o menos
     * del envío; si no (17-10 leyó "2023-10-26" en una planilla del 16-10-26), se toma la hora de envío:
     * un cierre de noche enviado antes del mediodía es de la noche anterior.
     *
     * @return array{0: string, 1: bool} [fecha, fue corregida]
     */
    public static function fechaOperativa(?string $fechaIa, string $tipoTurno, \DateTimeInterface $enviado): array
    {
        $local = \Carbon\Carbon::instance($enviado)->setTimezone('America/La_Paz');
        $esperada = ($tipoTurno === 'noche' && $local->hour < 14) ? $local->copy()->subDay() : $local->copy();

        try {
            $leida = $fechaIa ? \Carbon\Carbon::parse($fechaIa) : null;
        } catch (\Throwable) {
            $leida = null;
        }
        if ($leida && abs($leida->startOfDay()->diffInDays($esperada->copy()->startOfDay())) <= 2) {
            return [$leida->toDateString(), false];
        }

        return [$esperada->toDateString(), true];
    }

    /** Guarda la huella del archivo y devuelve el mensaje anterior (48 h) con la misma imagen, si existe. */
    private static function registrarHuella(WhatsAppMensajeInbound $inbound): ?WhatsAppMensajeInbound
    {
        if (empty($inbound->media_path)) {
            return null;
        }
        $ruta = collect([public_path($inbound->media_path), storage_path('app/public/' . str_replace('storage/', '', $inbound->media_path))])
            ->first(fn ($r) => is_file($r));
        if (!$ruta) {
            return null;
        }

        $inbound->hash_media = hash_file('sha256', $ruta);
        $inbound->save();

        return WhatsAppMensajeInbound::where('hash_media', $inbound->hash_media)
            ->where('id', '<', $inbound->id)
            ->where('created_at', '>=', now()->subHours(48))
            ->where('estado', '!=', 'duplicado')
            ->oldest('id')
            ->first();
    }

    /** Datos del reporte POS con las claves que devuelve la IA (antes se leían claves inexistentes y quedaban en 0). */
    private static function datosPos(array $datos, ?string $foto): array
    {
        return array_filter([
            'ventas_pos' => $datos['total_cobros_bs'] ?? $datos['total_ticket_bs'] ?? null,
            'ventas_efectivo_pos_bs' => $datos['ventas_efectivo_bs'] ?? null,
            'ventas_qr_bs' => $datos['ventas_qr_bs'] ?? $datos['qr_pos_bs'] ?? null,
            'ticket_pos_foto_url' => $foto,
        ], fn ($v) => $v !== null);
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));

        return str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $texto);
    }
}
