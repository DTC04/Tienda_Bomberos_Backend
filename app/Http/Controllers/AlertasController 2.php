<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PteStock;
use App\Models\MpMaterial;
use App\Models\PteMovimiento;
use Carbon\Carbon;

class AlertasController extends Controller
{
    // ==========================================
    // 1. CONFIGURACIÓN
    // ==========================================

    public function getConfig()
    {
        $configs = DB::table('alerta_configs')->pluck('valor', 'clave');

        return response()->json([
            'horario' => json_decode($configs['horario'] ?? '"08:30"'),
            'dias' => json_decode($configs['dias'] ?? '[]'),
            'emails_pte' => json_decode($configs['emails_pte'] ?? '[]'),
            'emails_mp' => json_decode($configs['emails_mp'] ?? '[]'),
            'activar_prediccion' => json_decode($configs['activar_prediccion'] ?? 'true')
        ]);
    }

    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'horario' => 'required|string',
            'dias' => 'required|array',
            'emails_pte' => 'required|array',
            'emails_mp' => 'required|array',
            'activar_prediccion' => 'boolean'
        ]);

        foreach ($data as $key => $value) {
            DB::table('alerta_configs')->updateOrInsert(
                ['clave' => $key],
                ['valor' => json_encode($value), 'updated_at' => now()]
            );
        }

        return response()->json(['message' => 'Configuración guardada correctamente']);
    }

    // ==========================================
    // 2. DATOS + PREDICCIÓN
    // ==========================================

    public function getCriticos(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $usarPrediccion = json_decode(DB::table('alerta_configs')->where('clave', 'activar_prediccion')->value('valor') ?? 'true');
        $diasAnalisis = 30;
        $fechaInicio = Carbon::now()->subDays($diasAnalisis);

        // --- PTE ---
        $queryPte = PteStock::with(['skuProducto.subfamilia', 'skuProducto.talla'])
            ->whereColumn('cantidad', '<=', 'stock_critico');

        $paginatorPte = $queryPte->paginate($perPage, ['*'], 'page_pte');

        $itemsPte = $paginatorPte->getCollection()->map(function ($stock) use ($usarPrediccion, $fechaInicio, $diasAnalisis) {
            $prod = $stock->skuProducto;
            $diasRestantes = 'N/A';
            $tendencia = 'normal';

            if ($usarPrediccion && $stock->cantidad > 0) {
                $consumoTotal = PteMovimiento::where('sku', $stock->sku)
                    ->where('fecha_hora', '>=', $fechaInicio)
                    ->whereIn('tipo_movimiento', ['AJUSTE_MANUAL', 'SALIDA_VENTA', 'AJUSTE_INVENTARIO'])
                    ->whereColumn('saldo_anterior', '>', 'saldo_nuevo')
                    ->sum('cantidad');
                $consumoTotal = abs($consumoTotal);

                if ($consumoTotal > 0) {
                    $consumoDiario = $consumoTotal / $diasAnalisis;
                    $dias = $stock->cantidad / $consumoDiario;
                    if ($dias < 1) {
                        $diasRestantes = "Menos de 1 día";
                        $tendencia = 'critico';
                    } else {
                        $diasRestantes = round($dias, 0) . " días aprox";
                        if ($dias < 7)
                            $tendencia = 'critico';
                    }
                } else {
                    $diasRestantes = "Estancado";
                }
            } elseif ($stock->cantidad == 0) {
                $diasRestantes = "Agotado";
                $tendencia = 'critico';
            }

            return [
                'sku' => $stock->sku,
                'nombre' => $prod ? $prod->nombre : 'Desconocido',
                'talla' => $prod && $prod->talla ? $prod->talla->nombre : 'N/A',
                'detalle' => $prod && $prod->subfamilia ? $prod->subfamilia->nombre : 'Sin familia',
                'stock_actual' => $stock->cantidad,
                'stock_critico' => $stock->stock_critico,
                'dias_para_quiebre' => $diasRestantes,
                'tendencia' => $tendencia,
                'unidad' => 'UN'
            ];
        });

        $pagPteData = $paginatorPte->toArray();
        $pagPteData['data'] = $itemsPte;

        // --- MP ---
        // Note: MP filtering happens after sum, using having or subquery for pagination consistency
        $queryMp = MpMaterial::withSum([
            'lotes' => function ($q) {
                $q->where('cantidad_actual', '>', 0);
            }
        ], 'cantidad_actual')
            ->whereHas('lotes', function ($q) {
                $q->where('cantidad_actual', '>', 0);
            }, '<=', DB::raw('stock_minimo')) // This is a bit tricky in Eloquent for post-aggregation filtering
            // Let's use a simpler approach: get all and manually paginate if complex, 
            // but let's try to filter via subquery for efficiency.
            ->orWhereDoesntHave('lotes') // Items with 0 stock that have min stock > 0
            ->where('stock_minimo', '>', 0);

        // However, the original logic fetched ALL and filtered in collection.
        // Let's maintain that but wrap it in a manual paginator for the response structure.
        $allMp = MpMaterial::withSum([
            'lotes' => function ($q) {
                $q->where('cantidad_actual', '>', 0);
            }
        ], 'cantidad_actual')
            ->with('unidad')
            ->get()
            ->filter(function ($m) {
                return ($m->lotes_sum_cantidad_actual ?? 0) <= $m->stock_minimo;
            })
            ->values()
            ->map(function ($m) {
                return [
                    'sku' => $m->codigo_interno,
                    'nombre' => $m->nombre_base,
                    'detalle' => 'Materia Prima',
                    'stock_actual' => $m->lotes_sum_cantidad_actual ?? 0,
                    'stock_critico' => $m->stock_minimo,
                    'unidad' => $m->unidad ? $m->unidad->abreviacion : 'Ud',
                    'dias_para_quiebre' => 'N/A',
                    'tendencia' => 'normal'
                ];
            });

        $totalMp = $allMp->count();
        $pageMp = $request->input('page_mp', 1);
        $paginatedMp = $allMp->forPage($pageMp, $perPage)->values();

        $pagMpData = [
            'current_page' => (int) $pageMp,
            'data' => $paginatedMp,
            'last_page' => ceil($totalMp / $perPage),
            'total' => $totalMp,
            'per_page' => (int) $perPage
        ];

        $configData = $this->getConfig()->original;

        return response()->json([
            'pte' => $pagPteData,
            'mp' => $pagMpData,
            'config' => $configData
        ]);
    }

    // ==========================================
    // 3. ENVÍO DE REPORTES (CORREGIDO)
    // ==========================================

    public function enviarReporte(Request $request)
    {
        try {
            $request->validate([
                'emails' => 'required|array',
                'emails.*' => 'email',
                'tipo' => 'nullable|string'
            ]);

            $tipo = $request->input('tipo', 'todo');
            $pte = null;
            $mp = null;

            // 1. Obtener Datos (Igual que antes)
            if ($tipo === 'todo' || $tipo === 'pte') {
                $pte = PteStock::with(['skuProducto.talla'])
                    ->whereColumn('cantidad', '<=', 'stock_critico')
                    ->get();
            }

            if ($tipo === 'todo' || $tipo === 'mp') {
                $mp = MpMaterial::withSum([
                    'lotes' => function ($q) {
                        $q->where('cantidad_actual', '>', 0);
                    }
                ], 'cantidad_actual')
                    ->get()
                    ->filter(function ($m) {
                        return ($m->lotes_sum_cantidad_actual ?? 0) <= $m->stock_minimo;
                    });
            }

            // 2. Generar PDF (UNA SOLA VEZ FUERA DEL BUCLE)
            // Esto soluciona el error 500 al enviar múltiples correos
            $pdf = Pdf::loadView('emails.alerta_stock', [
                'pte' => $pte,
                'mp' => $mp,
                'fecha' => now()->format('d/m/Y H:i'),
                'tipo' => $tipo
            ]);

            // Renderizamos el binario del PDF aquí para no re-procesarlo en cada vuelta
            $pdfContent = $pdf->output();

            // 3. Enviar Bucle usando el contenido ya generado
            foreach ($request->emails as $email) {
                // Usamos trim para evitar errores de espacios en blanco
                $destinatario = trim($email);

                // Pasamos $pdfContent en el use() en lugar de $pdf
                Mail::send([], [], function ($message) use ($destinatario, $pdfContent) {
                    $message->to($destinatario)
                        ->subject('🚨 Reporte de Stock Crítico - Tienda Bomberos')
                        ->attachData($pdfContent, 'reporte_alertas.pdf', [
                            'mime' => 'application/pdf',
                        ])
                        ->html('
                                <h3>Reporte de Stock Crítico</h3>
                                <p>Se adjunta el reporte solicitado.</p>
                            ');
                });
            }

            return response()->json(['message' => 'Reporte enviado con éxito']);

        } catch (\Exception $e) {
            Log::error("Error enviando reporte: " . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}