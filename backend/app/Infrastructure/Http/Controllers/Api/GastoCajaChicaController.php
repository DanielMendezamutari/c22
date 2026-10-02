<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\AI\AuditoriaGastosService;
use App\Infrastructure\Persistence\Eloquent\Models\GastoCajaChica;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GastoCajaChicaController extends Controller
{
    private AuditoriaGastosService $gastosService;

    public function __construct(AuditoriaGastosService $gastosService)
    {
        $this->gastosService = $gastosService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = GastoCajaChica::with(['sucursal', 'aprobadoPor'])
            ->latest('fecha');

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        if ($request->filled('fecha')) {
            $query->where('fecha', $request->input('fecha'));
        }

        if ($request->filled('estado_comprobante')) {
            $query->where('estado_comprobante', $request->input('estado_comprobante'));
        }

        $gastos = $query->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $gastos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'fecha' => 'required|date',
            'concepto' => 'required|string|max:255',
            'monto_bs' => 'required|numeric|min:0.01',
            'categoria' => 'nullable|in:hielo,limpieza,taxi_personal,mantenimiento,insumos_barra,otros',
            'foto' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'foto_base64' => 'nullable|string',
            'foto_url' => 'nullable|string',
        ]);

        try {
            $fotoUrl = null;
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('recibos_gastos', 'public');
                $fotoUrl = Storage::url($path);
            } elseif ($request->filled('foto_base64')) {
                $fotoUrl = 'data:image/jpeg;base64,' . substr($request->input('foto_base64'), 0, 30) . '...';
            } elseif ($request->filled('foto_url')) {
                $fotoUrl = $request->input('foto_url');
            }

            $estado = !empty($fotoUrl) ? 'aprobado_con_foto' : 'observado_sin_comprobante';

            $gasto = GastoCajaChica::create([
                'sucursal_id' => (int) $request->input('sucursal_id'),
                'turno_id' => $request->input('turno_id'),
                'fecha' => Carbon::parse($request->input('fecha'))->toDateString(),
                'concepto' => $request->input('concepto'),
                'categoria' => $request->input('categoria', 'otros'),
                'monto_bs' => (float) $request->input('monto_bs'),
                'es_reposicion_de_ventas' => true,
                'foto_comprobante_url' => $fotoUrl,
                'estado_comprobante' => $estado,
                'emparejado_ocr' => !empty($fotoUrl),
                'observaciones' => $request->input('observaciones'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gasto de caja chica registrado correctamente.',
                'data' => $gasto,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            $gasto = GastoCajaChica::findOrFail($id);

            $gasto->estado_comprobante = 'aprobado_con_foto';
            $gasto->aprobado_por_id = $user?->id ?? 1;
            $gasto->aprobado_at = Carbon::now();
            $gasto->observaciones = ($gasto->observaciones ? $gasto->observaciones . ' | ' : '') . 'Aprobado manualmente en plataforma web.';
            $gasto->save();

            return response()->json([
                'success' => true,
                'message' => "Gasto #{$id} ({$gasto->concepto}) aprobado satisfactoriamente.",
                'data' => $gasto,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function pendientes(Request $request): JsonResponse
    {
        $pendientes = GastoCajaChica::with(['sucursal'])
            ->whereIn('estado_comprobante', ['observado_sin_comprobante', 'pendiente_revision'])
            ->latest('fecha')
            ->get();

        return response()->json([
            'success' => true,
            'total_pendientes' => $pendientes->count(),
            'total_monto_observado_bs' => $pendientes->sum('monto_bs'),
            'data' => $pendientes,
        ]);
    }

    public function cruzarConPlanilla(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'fecha' => 'required|date',
        ]);

        try {
            $resultado = $this->gastosService->cruzarGastosConPlanilla(
                (int) $request->input('sucursal_id'),
                $request->input('fecha')
            );

            return response()->json([
                'success' => true,
                'message' => 'Cruce de recibos fotográficos contra planilla completado.',
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
