<?php

namespace App\Http\Controllers;

use App\Models\PteStock;
use App\Models\PteMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\PteSku;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class MovimientoController extends Controller
{
    /**
     * READ (Listar): Ver historial solo de INGRESOS
     */
    public function indexIngresos()
    {
        $ingresos = PteMovimiento::where('tipo_movimiento', 'INGRESO_BODEGA')
            ->with('skuProducto.talla')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($ingresos);
    }

    public function show($id)
    {
        $movimiento = PteMovimiento::find($id);

        if (!$movimiento) {
            return response()->json(['message' => 'Movimiento no encontrado'], 404);
        }

        return response()->json($movimiento);
    }

    public function storeIngreso(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|exists:pte_skus,sku',
            'cantidad' => 'required|integer|min:1',
            'observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $sku = $request->sku;
            $cantidad = $request->cantidad;

            $stock = PteStock::firstOrCreate(
                ['sku' => $sku],
                ['cantidad' => 0]
            );

            $saldoAnterior = $stock->cantidad;
            $stock->cantidad += $cantidad;
            $stock->save();

            $movimiento = PteMovimiento::create([
                'sku' => $sku,
                'tipo_movimiento' => 'INGRESO_BODEGA',
                'cantidad' => $cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $stock->cantidad,
                'fecha_hora' => now(),
                'usuario_id' => Auth::id(),
                'id_lote' => (string) Str::uuid()
            ]);

            return response()->json([
                'message' => 'Ingreso registrado correctamente',
                'data' => $movimiento
            ], 201);
        });
    }

    public function anularIngreso(Request $request, $id)
    {
        return DB::transaction(function () use ($id, $request) {
            $movimientoOriginal = PteMovimiento::find($id);

            if (!$movimientoOriginal) {
                return response()->json(['message' => 'Movimiento no encontrado'], 404);
            }

            if ($movimientoOriginal->tipo_movimiento !== 'INGRESO_BODEGA') {
                return response()->json(['message' => 'Solo se pueden anular Ingresos de Bodega aquí'], 400);
            }

            $stock = PteStock::where('sku', $movimientoOriginal->sku)->first();

            if ($stock->cantidad < $movimientoOriginal->cantidad) {
                return response()->json([
                    'message' => 'No se puede anular: El stock actual es menor a lo que se ingresó.'
                ], 400);
            }

            // Restamos stock físico
            $saldoAnterior = $stock->cantidad;
            $stock->cantidad -= $movimientoOriginal->cantidad;
            $stock->save();

            // Obtenemos directamente el usuario autenticado
            $usuarioId = Auth::id();

            $anulacion = PteMovimiento::create([
                'sku' => $movimientoOriginal->sku,
                'tipo_movimiento' => 'ANULACION_INGRESO',
                'cantidad' => $movimientoOriginal->cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $stock->cantidad,
                'fecha_hora' => now(),
                'usuario_id' => $usuarioId,
                'id_lote' => (string) Str::uuid()
            ]);

            return response()->json([
                'message' => 'Ingreso anulado y stock corregido',
                'data' => $anulacion
            ]);
        });
    }

    public function storeSalidaCotizacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|exists:pte_skus,sku',
            'cantidad' => 'required|integer|min:1',
            'cotizacion_id' => 'required|integer',
            'observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $sku = $request->sku;
            $cantidad = $request->cantidad;

            $stock = PteStock::where('sku', $sku)->first();

            if (!$stock) {
                return response()->json(['message' => 'Error: El producto no tiene registro de stock inicial.'], 404);
            }

            // Validar que hay suficiente stock físico
            if ($stock->cantidad < $cantidad) {
                return response()->json([
                    'message' => "Stock físico insuficiente. Cantidad disponible: {$stock->cantidad}."
                ], 422);
            }

            $saldoAnterior = $stock->cantidad;
            $stock->cantidad -= $cantidad;
            $stock->save();

            $movimiento = PteMovimiento::create([
                'sku' => $sku,
                'tipo_movimiento' => 'SALIDA_VENTA',
                'cantidad' => $cantidad,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $stock->cantidad,
                'fecha_hora' => now(),
                'usuario_id' => Auth::id(),
                'cotizacion_id' => $request->cotizacion_id,
                'observacion' => $request->observacion ?? "Despacho por Cotización #{$request->cotizacion_id}",
                'id_lote' => (string) Str::uuid()
            ]);

            return response()->json([
                'message' => 'Salida registrada correctamente. Stock actualizado.',
                'data' => $movimiento
            ], 201);
        });
    }

    public function importarExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productos' => 'required|array',
            'productos.*.sku' => 'required|string',
            'productos.*.cantidad' => 'required|numeric',
            'productos.*.nombre' => 'nullable|string',
            'productos.*.precio_venta' => 'nullable|numeric',
            'productos.*.stock_critico' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $productos = $request->productos;
            $userId = Auth::id();
            $idLote = (string) Str::uuid();

            // Cargar Catálogos para validación rápida (evita errores de clave foránea 1452)
            $validIds = [
                'unidad' => \App\Models\PteUnidadNegocio::pluck('id')->toArray(),
                'origen' => \App\Models\PteOrigen::pluck('id')->toArray(),
                'grupo' => \App\Models\PteGrpFamilia::pluck('id')->toArray(),
                'familia' => \App\Models\PteFamilia::pluck('id')->toArray(),
                'subfamilia' => \App\Models\PteSubfamilia::pluck('id')->toArray(),
                'tipo' => \App\Models\PteFamiliaTipo::pluck('id')->toArray(),
                'formato' => \App\Models\PteFamiliaFormato::pluck('id')->toArray(),
                'genero' => \App\Models\PteGenero::pluck('id')->toArray(),
                'color' => \App\Models\PteColor::pluck('id')->toArray(),
                'talla' => \App\Models\PteTalla::pluck('id')->toArray(),
            ];

            $getSafe = function ($id, $key) use ($validIds) {
                $list = $validIds[$key] ?? [];
                if (in_array($id, $list))
                    return $id;
                if (in_array(0, $list))
                    return 0;
                if (in_array(1, $list))
                    return 1;
                return !empty($list) ? $list[0] : 0;
            };

            $procesados = 0;
            $actualizados = 0;
            $creados = 0;
            $ignorados = 0;
            $sinCambios = 0;
            $erroresDetalle = [];

            foreach ($productos as $item) {
                $sku = trim($item['sku']);
                $nuevaCantidad = intval($item['cantidad']);
                $nombre = $item['nombre'] ?? null;
                $precio = $item['precio_venta'] ?? null;
                $stockCritico = $item['stock_critico'] ?? 5;

                // 1. Verificar si existe el SKU en pte_skus
                $producto = PteSku::where('sku', $sku)->first();
                $stock = PteStock::where('sku', $sku)->first();

                if (!$producto) {
                    // SI NO EXISTE EL PRODUCTO, INTENTAMOS CREARLO
                    try {
                        // Validación básica del formato SKU
                        if (strlen($sku) < 14) {
                            throw new \Exception("Formato de SKU inválido (mínimo 14-15 dígitos)");
                        }

                        // Parseo de IDs desde el patrón del SKU
                        $u_raw = intval(substr($sku, 0, 1));
                        $o_raw = intval(substr($sku, 1, 1));
                        $g_raw = intval(substr($sku, 2, 1));
                        $f_raw = intval(substr($sku, 3, 1));
                        $sf_raw = intval(substr($sku, 4, 2));
                        $ti_raw = intval(substr($sku, 6, 2));
                        $ff_raw = intval(substr($sku, 8, 2));
                        // Ajuste formato según grupo
                        if ($g_raw === 1)
                            $ff_raw += 20;

                        $gen_raw = intval(substr($sku, 10, 1));
                        $c_raw = intval(substr($sku, 11, 2));
                        $ta_raw = intval(substr($sku, 13, 2));

                        $producto = PteSku::create([
                            'sku' => $sku,
                            'nombre' => $nombre ?? "Producto $sku",
                            'precio_venta' => $precio,
                            'stock_critico' => $stockCritico,
                            'unidad_negocio_id' => $getSafe($u_raw, 'unidad'),
                            'origen_id' => $getSafe($o_raw, 'origen'),
                            'grp_familia_id' => $getSafe($g_raw, 'grupo'),
                            'familia_id' => $getSafe($f_raw, 'familia'),
                            'subfamilia_id' => $getSafe($sf_raw, 'subfamilia'),
                            'familia_tipo_id' => $getSafe($ti_raw, 'tipo'),
                            'familia_formato_id' => $getSafe($ff_raw, 'formato'),
                            'genero_id' => $getSafe($gen_raw, 'genero'),
                            'color_id' => $getSafe($c_raw, 'color'),
                            'talla_id' => $getSafe($ta_raw, 'talla'),
                        ]);

                        $creados++;
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        $ignorados++;
                        if (count($erroresDetalle) < 10) {
                            $erroresDetalle[] = "SKU $sku: " . (Str::contains($msg, 'foreign key') ? "ID de categoría derivado no existe" : $msg);
                        }
                        continue;
                    }
                }

                // Asegurar que exista el registro de stock
                if (!$stock) {
                    $stock = PteStock::create(['sku' => $sku, 'cantidad' => 0, 'stock_critico' => $stockCritico]);
                }

                $saldoAnterior = $stock->cantidad;

                // 2. Verificar si hay cambio real o si es nuevo
                $esNuevo = ($saldoAnterior === 0 && !PteMovimiento::where('sku', $sku)->exists());
                $hasStockChange = ($saldoAnterior !== $nuevaCantidad);
                $hasCriticoChange = ($stock->stock_critico != $stockCritico);

                if ($hasStockChange || $hasCriticoChange || $esNuevo) {
                    $diferencia = $nuevaCantidad - $saldoAnterior;

                    $stock->cantidad = $nuevaCantidad;
                    $stock->stock_critico = $stockCritico;
                    $stock->save();

                    // Si incluimos precio en el excel, actualizamos el producto también
                    if ($producto) {
                        if ($precio)
                            $producto->precio_venta = $precio;
                        $producto->stock_critico = $stockCritico;
                        $producto->save();
                    }

                    PteMovimiento::create([
                        'sku' => $sku,
                        'tipo_movimiento' => $esNuevo ? 'INVENTARIO_INICIAL' : 'CAMBIOS_EXCEL',
                        'cantidad' => abs($diferencia),
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $nuevaCantidad,
                        'fecha_hora' => now(),
                        'usuario_id' => $userId,
                        'id_lote' => $idLote,
                        'observacion' => $esNuevo ? "Carga Inicial Excel" :
                            ($hasStockChange ? ($diferencia > 0 ? "Ajuste (+{$diferencia})" : "Ajuste ({$diferencia})") : "Ajuste Umbral Crítico")
                    ]);

                    if (!$esNuevo)
                        $actualizados++;
                } else {
                    $sinCambios++;
                }
                $procesados++;
            }

            return response()->json([
                'message' => 'Importación completada',
                'resumen' => [
                    'total_procesados' => $procesados,
                    'actualizados' => $actualizados,
                    'creados' => $creados,
                    'sin_cambios' => $sinCambios,
                    'skus_no_encontrados' => $ignorados,
                    'detalles_errores' => $erroresDetalle
                ]
            ]);
        });
    }

    public function historialPorSku($sku)
    {
        $movimientos = PteMovimiento::where('sku', $sku)
            ->orderBy('fecha_hora', 'desc')
            ->with(['skuProducto.talla', 'usuario']) // Cargar usuario
            ->get();

        return response()->json($movimientos);
    }

    public function historialGeneral(Request $request)
    {
        // 1. QUERY BASE PARA LOTES (Agrupando por id_lote o criterios de tiempo/usuario)
        // Usamos una subconsulta o agrupamos directamente para identificar los lotes únicos
        $subQuery = DB::table('pte_movimientos')
            ->select(
                'id_lote',
                'tipo_movimiento',
                'usuario_id',
                'cotizacion_id',
                DB::raw("DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i') as fecha_minuto"),
                DB::raw("MAX(fecha_hora) as ultima_fecha"),
                DB::raw("COUNT(*) as total_registros"),
                DB::raw("SUM(cantidad) as total_unidades")
            );

        // Filtros en la subconsulta
        if ($request->filled('tipo')) {
            $subQuery->where('tipo_movimiento', $request->tipo);
        }
        if ($request->filled('fecha')) {
            $subQuery->whereDate('fecha_hora', $request->fecha);
        }

        // Agrupación principal
        $subQuery->groupBy('id_lote', 'tipo_movimiento', 'usuario_id', 'cotizacion_id', 'fecha_minuto');

        // 2. PAGINACIÓN DE LOTES
        $perPage = min((int) $request->input('per_page', 50), 100); // Reducimos per_page a 50 lotes (más razonable)
        $page = max((int) $request->input('page', 1), 1);

        $totalLotes = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery)
            ->count();

        $lotesPaginados = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery)
            ->orderBy('ultima_fecha', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // 3. CARGAR RELACIONES Y FORMATEAR
        // Obtenemos los IDs para cargar relaciones de forma eficiente
        $userIds = $lotesPaginados->pluck('usuario_id')->unique();
        $cotizacionIds = $lotesPaginados->pluck('cotizacion_id')->filter()->unique();

        $usuarios = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');
        $cotizaciones = \App\Models\Cotizacion::with(['cliente', 'ejecutivo', 'detalles'])
            ->whereIn('id', $cotizacionIds)
            ->get()
            ->keyBy('id');

        $reporte = [];

        foreach ($lotesPaginados as $lote) {
            $usuario = $usuarios->get($lote->usuario_id);
            $cotizacion = $lote->cotizacion_id ? $cotizaciones->get($lote->cotizacion_id) : null;

            // Re-hidratar items solo si son pocos (<= 20)
            $items = null;
            if ($lote->total_registros <= 20) {
                // Buscamos los movimientos de este lote específico
                $movimientos = PteMovimiento::with(['skuProducto.talla', 'skuProducto.color'])
                    ->where('id_lote', $lote->id_lote)
                    ->get();

                $items = $movimientos->map(function ($mov) use ($cotizacion) {
                    return [
                        'sku' => $mov->sku,
                        'producto' => $mov->skuProducto ? $mov->skuProducto->nombre : '---',
                        'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : '-',
                        'color' => $mov->skuProducto && $mov->skuProducto->color ? $mov->skuProducto->color->nombre : '-',
                        'cantidad' => $mov->cantidad,
                        'saldo_anterior' => $mov->saldo_anterior,
                        'saldo_nuevo' => $mov->saldo_nuevo,
                        'hora_exacta' => \Carbon\Carbon::parse($mov->fecha_hora)->format('H:i:s'),
                        'precio_unitario' => ($cotizacion && $cotizacion->detalles->where('sku', $mov->sku)->first())
                            ? $cotizacion->detalles->where('sku', $mov->sku)->first()->precio_unitario
                            : ($mov->skuProducto ? $mov->skuProducto->precio_venta : 0),
                    ];
                });
            }

            $reporte[] = [
                'id_lote' => $lote->id_lote,
                'raw_fecha' => $lote->fecha_minuto,
                'raw_usuario_id' => $lote->usuario_id,
                'raw_tipo' => $lote->tipo_movimiento,
                'fecha_hora_grupo' => \Carbon\Carbon::parse($lote->ultima_fecha)->format('d/m/Y H:i'),
                'usuario' => $usuario ? $usuario->name : ($lote->usuario_id ? 'Usuario ID: ' . $lote->usuario_id : 'SISTEMA (Anónimo)'),
                'tipo_movimiento' => $lote->tipo_movimiento,
                'cotizacion_id' => $lote->cotizacion_id,
                'cotizacion_info' => $cotizacion ? [
                    'numero' => $cotizacion->numero,
                    'fecha' => $cotizacion->fecha_creacion ? $cotizacion->fecha_creacion->format('d/m/Y') : null,
                    'ejecutivo' => $cotizacion->ejecutivo ? $cotizacion->ejecutivo->name : null,
                    'cliente' => $cotizacion->cliente ? [
                        'nombre_empresa' => $cotizacion->cliente->nombre_empresa,
                        'nombre_contacto' => $cotizacion->cliente->nombre_contacto,
                        'telefono' => $cotizacion->cliente->telefono,
                        'correo' => $cotizacion->cliente->correo,
                    ] : null,
                ] : null,
                'cliente_nombre' => $cotizacion && $cotizacion->cliente ? $cotizacion->cliente->nombre_empresa : null,
                'total_registros' => $lote->total_registros,
                'total_unidades' => $lote->total_unidades,
                'items' => $items
            ];
        }

        return response()->json([
            'data' => $reporte,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalLotes,
                'last_page' => (int) ceil($totalLotes / $perPage),
            ]
        ]);
    }

    /**
     * Fetch items for a specific lote on demand (lazy loading for large lotes)
     */
    public function loteItems(Request $request)
    {
        $loteId = $request->query('id_lote');
        $fecha = $request->query('fecha');
        $userId = $request->query('usuario');
        $tipo = $request->query('tipo');

        $query = PteMovimiento::with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.detalles']);

        // Prioridad al id_lote real
        if ($loteId && strlen($loteId) > 10) {
            $query->where('id_lote', $loteId);
        } else {
            // Fallback para registros antiguos o sin UUID
            if (!$fecha || !$userId || !$tipo) {
                return response()->json(['error' => 'Faltan parámetros para identificar el lote'], 400);
            }
            $query->where('usuario_id', $userId)
                ->where('tipo_movimiento', $tipo)
                ->where('fecha_hora', 'like', $fecha . '%');
        }

        $movimientos = $query->orderBy('fecha_hora', 'asc')->get();

        $first = $movimientos->first();

        $items = $movimientos->map(function ($mov) use ($first) {
            return [
                'sku' => $mov->sku,
                'producto' => $mov->skuProducto ? $mov->skuProducto->nombre : '---',
                'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : '-',
                'color' => $mov->skuProducto && $mov->skuProducto->color ? $mov->skuProducto->color->nombre : '-',
                'cantidad' => $mov->cantidad,
                'saldo_anterior' => $mov->saldo_anterior,
                'saldo_nuevo' => $mov->saldo_nuevo,
                'hora_exacta' => \Carbon\Carbon::parse($mov->fecha_hora)->format('H:i:s'),
                'precio_unitario' => $first && $first->cotizacion && $first->cotizacion->detalles->where('sku', $mov->sku)->first()
                    ? $first->cotizacion->detalles->where('sku', $mov->sku)->first()->precio_unitario
                    : ($mov->skuProducto ? $mov->skuProducto->precio_venta : 0),
            ];
        });

        return response()->json($items);
    }

    /**
     * PDF POR SKU (Historial de Producto)
     */
    public function descargarPdfPorSku($sku)
    {
        $movimientos = PteMovimiento::with(['skuProducto.talla', 'skuProducto.color', 'usuario', 'cotizacion.cliente', 'cotizacion.ejecutivo', 'cotizacion.detalles'])
            ->where('sku', $sku)
            ->orderBy('fecha_hora', 'desc')
            ->get();

        if ($movimientos->isEmpty()) {
            return response()->json(['error' => 'No hay movimientos para este SKU'], 404);
        }

        // Agrupamos igual que en el reporte general para que el USUARIO salga en el encabezado del bloque (Arriba)
        // y el producto salga en las filas (Abajo)
        $agrupados = $movimientos->groupBy(function ($item) {
            $fechaMinuto = \Carbon\Carbon::parse($item->fecha_hora)->format('Y-m-d H:i');
            return $fechaMinuto . '|' . $item->usuario_id . '|' . $item->tipo_movimiento;
        });

        $reporte = [];

        foreach ($agrupados as $clave => $items) {
            $first = $items->first();

            $reporte[] = [
                'fecha_hora_grupo' => \Carbon\Carbon::parse($first->fecha_hora)->format('d/m/Y H:i'),
                'usuario' => $first->usuario ? $first->usuario->name : ($first->usuario_id ? 'Usuario ID: ' . $first->usuario_id : 'SISTEMA (Anónimo)'),
                'tipo_movimiento' => $first->tipo_movimiento,
                'cotizacion_id' => $first->cotizacion_id,
                'cotizacion_info' => $first->cotizacion ? [
                    'numero' => $first->cotizacion->numero,
                    'fecha' => $first->cotizacion->fecha_creacion ? $first->cotizacion->fecha_creacion->format('d/m/Y') : null,
                    'ejecutivo' => $first->cotizacion->ejecutivo ? $first->cotizacion->ejecutivo->name : null,
                    'cliente' => $first->cotizacion->cliente ? [
                        'nombre_empresa' => $first->cotizacion->cliente->nombre_empresa,
                        'nombre_contacto' => $first->cotizacion->cliente->nombre_contacto,
                        'telefono' => $first->cotizacion->cliente->telefono,
                        'correo' => $first->cotizacion->cliente->correo,
                    ] : null,
                ] : null,
                'cliente_nombre' => $first->cotizacion && $first->cotizacion->cliente ? $first->cotizacion->cliente->nombre_empresa : null,
                'total_registros' => $items->count(),
                'total_unidades' => $items->sum('cantidad'),
                'solicitante' => ($first->tipo_movimiento === 'SALIDA_VENTA' && $first->cotizacion_id)
                    ? (\App\Models\Peticion::where('cotizacion_id', $first->cotizacion_id)
                        ->with('user')
                        ->first()->user->name ?? null)
                    : null,
                'items' => $items->map(function ($mov) use ($first) {
                    return [
                        'sku' => $mov->sku,
                        'producto' => $mov->skuProducto ? $mov->skuProducto->nombre : '---',
                        'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : '-',
                        'color' => $mov->skuProducto && $mov->skuProducto->color ? $mov->skuProducto->color->nombre : '-',
                        'cantidad' => $mov->cantidad,
                        'saldo_anterior' => $mov->saldo_anterior,
                        'saldo_nuevo' => $mov->saldo_nuevo,
                        'hora_exacta' => \Carbon\Carbon::parse($mov->fecha_hora)->format('H:i:s'),
                        'precio_unitario' => $first->cotizacion && $first->cotizacion->detalles->where('sku', $mov->sku)->first()
                            ? $first->cotizacion->detalles->where('sku', $mov->sku)->first()->precio_unitario
                            : ($mov->skuProducto ? $mov->skuProducto->precio_venta : 0),
                    ];
                })
            ];
        }

        // Usamos la vista estándar (sin tipo_reporte='historial_sku') para que renderice columnas de productos
        $pdf = Pdf::loadView('pdf.bitacora', ['reporte' => $reporte]);

        return $pdf->stream("historial_{$sku}.pdf");
    }

    /**
     * PDF INDIVIDUAL
     */
    public function descargarPdfIndividual(Request $request)
    {
        $fecha = $request->query('fecha');
        $userId = $request->query('usuario');
        $tipo = $request->query('tipo');

        if (!$fecha || !$userId || !$tipo) {
            return response()->json(['error' => 'Faltan parámetros'], 400);
        }

        $movimientos = PteMovimiento::with(['skuProducto.talla', 'skuProducto.color', 'usuario', 'cotizacion.cliente', 'cotizacion.ejecutivo', 'cotizacion.detalles'])
            ->where('usuario_id', $userId)
            ->where('tipo_movimiento', $tipo)
            ->where('fecha_hora', 'like', $fecha . '%')
            ->orderBy('fecha_hora', 'asc')
            ->get();

        if ($movimientos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron datos para este lote'], 404);
        }

        $first = $movimientos->first();

        $reporteUnico = [
            [
                'fecha_hora_grupo' => \Carbon\Carbon::parse($first->fecha_hora)->format('d/m/Y H:i'),
                'usuario' => $first->usuario ? $first->usuario->name : ($first->usuario_id ? 'Usuario ID: ' . $first->usuario_id : 'SISTEMA (Anónimo)'),
                'tipo_movimiento' => $first->tipo_movimiento,
                'cotizacion_id' => $first->cotizacion_id,
                'cotizacion_info' => $first->cotizacion ? [
                    'numero' => $first->cotizacion->numero,
                    'fecha' => $first->cotizacion->fecha_creacion ? $first->cotizacion->fecha_creacion->format('d/m/Y') : null,
                    'ejecutivo' => $first->cotizacion->ejecutivo ? $first->cotizacion->ejecutivo->name : null,
                    'cliente' => $first->cotizacion->cliente ? [
                        'nombre_empresa' => $first->cotizacion->cliente->nombre_empresa,
                        'nombre_contacto' => $first->cotizacion->cliente->nombre_contacto,
                        'telefono' => $first->cotizacion->cliente->telefono,
                        'correo' => $first->cotizacion->cliente->correo,
                    ] : null,
                ] : null,
                'cliente_nombre' => $first->cotizacion && $first->cotizacion->cliente ? $first->cotizacion->cliente->nombre_empresa : null,
                'total_registros' => $movimientos->count(),
                'total_unidades' => $movimientos->sum('cantidad'),
                'solicitante' => ($first->tipo_movimiento === 'SALIDA_VENTA' && $first->cotizacion_id)
                    ? (\App\Models\Peticion::where('cotizacion_id', $first->cotizacion_id)
                        ->with('user')
                        ->first()->user->name ?? null)
                    : null,
                'items' => $movimientos->map(function ($mov) use ($first) {
                    return [
                        'sku' => $mov->sku,
                        'producto' => $mov->skuProducto ? $mov->skuProducto->nombre : '---',
                        'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : '-',
                        'color' => $mov->skuProducto && $mov->skuProducto->color ? $mov->skuProducto->color->nombre : '-',
                        'cantidad' => $mov->cantidad,
                        'saldo_anterior' => $mov->saldo_anterior,
                        'saldo_nuevo' => $mov->saldo_nuevo,
                        'hora_exacta' => \Carbon\Carbon::parse($mov->fecha_hora)->format('H:i:s'),
                        'usuario_nombre' => $mov->usuario ? $mov->usuario->name : 'SISTEMA',
                        'precio_unitario' => $first->cotizacion && $first->cotizacion->detalles->where('sku', $mov->sku)->first()
                            ? $first->cotizacion->detalles->where('sku', $mov->sku)->first()->precio_unitario
                            : ($mov->skuProducto ? $mov->skuProducto->precio_venta : 0),
                    ];
                })
            ]
        ];

        $pdf = Pdf::loadView('pdf.bitacora', [
            'reporte' => $reporteUnico,
            'tipo_reporte' => 'general'
        ]);

        $tipoLimpio = str_replace(' ', '_', $first->tipo_movimiento);
        $timestamp = date('d-m-Y_H-i-s');
        $nombreArchivo = "comprobante_{$tipoLimpio}_{$timestamp}.pdf";

        return $pdf->stream($nombreArchivo);
    }

    /**
     * PDF GENERAL
     */
    public function descargarPdf()
    {
        $movimientos = PteMovimiento::with(['skuProducto.talla', 'skuProducto.color', 'usuario', 'cotizacion.cliente', 'cotizacion.ejecutivo', 'cotizacion.detalles'])
            ->orderBy('fecha_hora', 'desc')
            ->limit(1000)
            ->get();

        $agrupados = $movimientos->groupBy(function ($item) {
            $fechaMinuto = \Carbon\Carbon::parse($item->fecha_hora)->format('Y-m-d H:i');
            return $fechaMinuto . '|' . $item->usuario_id . '|' . $item->tipo_movimiento;
        });

        $reporte = [];
        foreach ($agrupados as $clave => $items) {
            $first = $items->first();
            $reporte[] = [
                'fecha_hora_grupo' => \Carbon\Carbon::parse($first->fecha_hora)->format('d/m/Y H:i'),
                'usuario' => $first->usuario ? $first->usuario->name : ($first->usuario_id ? 'Usuario ID: ' . $first->usuario_id : 'SISTEMA (Anónimo)'),
                'tipo_movimiento' => $first->tipo_movimiento,
                'cotizacion_id' => $first->cotizacion_id,
                'cotizacion_info' => $first->cotizacion ? [
                    'numero' => $first->cotizacion->numero,
                    'fecha' => $first->cotizacion->fecha_creacion ? $first->cotizacion->fecha_creacion->format('d/m/Y') : null,
                    'ejecutivo' => $first->cotizacion->ejecutivo ? $first->cotizacion->ejecutivo->name : null,
                    'cliente' => $first->cotizacion->cliente ? [
                        'nombre_empresa' => $first->cotizacion->cliente->nombre_empresa,
                        'nombre_contacto' => $first->cotizacion->cliente->nombre_contacto,
                        'telefono' => $first->cotizacion->cliente->telefono,
                        'correo' => $first->cotizacion->cliente->correo,
                    ] : null,
                ] : null,
                'cliente_nombre' => $first->cotizacion && $first->cotizacion->cliente ? $first->cotizacion->cliente->nombre_empresa : null,
                'total_registros' => $items->count(),
                'total_unidades' => $items->sum('cantidad'),
                'solicitante' => ($first->tipo_movimiento === 'SALIDA_VENTA' && $first->cotizacion_id)
                    ? (\App\Models\Peticion::where('cotizacion_id', $first->cotizacion_id)
                        ->with('user')
                        ->first()->user->name ?? null)
                    : null,
                'items' => $items->map(function ($mov) use ($first) {
                    return [
                        'sku' => $mov->sku,
                        'producto' => $mov->skuProducto ? $mov->skuProducto->nombre : '---',
                        'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : '-',
                        'color' => $mov->skuProducto && $mov->skuProducto->color ? $mov->skuProducto->color->nombre : '-',
                        'cantidad' => $mov->cantidad,
                        'saldo_anterior' => $mov->saldo_anterior,
                        'saldo_nuevo' => $mov->saldo_nuevo,
                        'hora_exacta' => \Carbon\Carbon::parse($mov->fecha_hora)->format('H:i:s'),
                        'usuario_nombre' => $mov->usuario ? $mov->usuario->name : 'SISTEMA',
                        'precio_unitario' => $first->cotizacion && $first->cotizacion->detalles->where('sku', $mov->sku)->first()
                            ? $first->cotizacion->detalles->where('sku', $mov->sku)->first()->precio_unitario
                            : ($mov->skuProducto ? $mov->skuProducto->precio_venta : 0),
                    ];
                })
            ];
        }

        $pdf = Pdf::loadView('pdf.bitacora', [
            'reporte' => $reporte,
            'tipo_reporte' => 'general'
        ]);
        return $pdf->download('bitacora_movimientos.pdf');
    }
}