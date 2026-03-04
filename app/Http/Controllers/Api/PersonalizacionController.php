<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Personalizacion;
use App\Models\PteMovimiento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonalizacionController extends Controller
{
    /**
     * Listar todas las personalizaciones (para el Kanban).
     *
     * Query params:
     *  - estado      : filtrar por estado
     *  - search      : texto libre (sku / nombre)
     *  - per_page    : tarjetas por página (default 100, max 200)
     *  - page        : página actual (default 1)
     *  - slim        : 1 = omite configuracion.layers del listado (recomendado para Kanban)
     */
    public function index(Request $request)
    {
        // ── 1. Sólo las columnas necesarias para las tarjetas Kanban ─────────
        $select = [
            'id', 'pte_movimiento_id', 'cotizacion_id', 'user_id',
            'sku', 'producto_nombre', 'cantidad', 'estado',
            'tipo', 'prioridad', 'notas', 'created_at', 'updated_at',
            // configuracion se procesa abajo para eliminar las imágenes base64 en modo slim
            'configuracion',
        ];

        // ── 2. Eager loading reducido: sólo lo imprescindible para el Kanban ─
        $query = Personalizacion::select($select)
            ->with([
                'pteMovimiento:id,cotizacion_id,sku,tipo_movimiento',
                'pteMovimiento.skuProducto:sku,nombre,color_id,talla_id,familia_id,subfamilia_id',
                'pteMovimiento.skuProducto.color:id,nombre',
                'pteMovimiento.skuProducto.talla:id,nombre',
                'pteMovimiento.skuProducto.familia:id,nombre',
                'pteMovimiento.skuProducto.subfamilia:id,nombre',
            ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('producto_nombre', 'like', "%{$search}%");
            });
        }

        // ── 3. Paginación ────────────────────────────────────────────────────
        $perPage = min((int) $request->get('per_page', 100), 200);
        $paginated = $query->paginate($perPage);

        // ── 4. Pre-fetch DetalleCotizacion en batch (elimina N+1) ────────────
        //   Sólo para los registros que no tienen talla via pteMovimiento
        $cotizacionIds = $paginated->getCollection()
            ->whereNull('pteMovimiento')        // sin movimiento → via cotización
            ->pluck('cotizacion_id')
            ->filter()
            ->unique()
            ->values();

        $tallasMap = [];
        if ($cotizacionIds->isNotEmpty()) {
            $tallasMap = \App\Models\DetalleCotizacion::whereIn('cotizacion_id', $cotizacionIds)
                ->whereIn('sku', $paginated->getCollection()->pluck('sku')->unique())
                ->get(['cotizacion_id', 'sku', 'talla'])
                ->mapWithKeys(fn($d) => ["{$d->cotizacion_id}_{$d->sku}" => $d->talla])
                ->toArray();
        }

        $isSlim = $request->boolean('slim', true); // slim=true por omisión para el Kanban

        $items = $paginated->getCollection()->map(function ($item) use ($tallasMap, $isSlim) {
            $skuProd = $item->pteMovimiento?->skuProducto;

            $item->color      = $skuProd?->color?->nombre;
            $item->familia    = $skuProd?->familia?->nombre;
            $item->subfamilia = $skuProd?->subfamilia?->nombre;
            $item->talla      = $skuProd?->talla?->nombre
                ?? $tallasMap["{$item->cotizacion_id}_{$item->sku}"]
                ?? null;

            // ── 5. API Slimming: saca las imágenes base64 de layers ──────────
            //   Sólo se trunca si `content` es un base64 (legacy).
            //   Si ya es una URL (/storage/...), se pasa tal cual — es un string corto.
            if ($isSlim && is_array($item->configuracion)) {
                $config = $item->configuracion;

                if (!empty($config['layers'])) {
                    $config['layers'] = array_map(function ($l) {
                        $content = $l['content'] ?? null;
                        $isBase64 = $content && str_starts_with($content, 'data:');

                        return [
                            'id'      => $l['id']   ?? null,
                            'type'    => $l['type'] ?? null,
                            'view'    => $l['view'] ?? null,
                            // Mantener URL corta; descartar base64 pesado
                            'content' => $isBase64 ? null : $content,
                        ];
                    }, $config['layers']);
                }

                // También limpiar imágenes base64 dentro de definicion_diseno
                if (!empty($config['definicion_diseno']) && is_array($config['definicion_diseno'])) {
                    foreach ($config['definicion_diseno'] as $zona => &$data) {
                        if (isset($data['image']) && str_starts_with($data['image'], 'data:')) {
                            $data['image'] = null; // se carga completo en show()
                        }
                    }
                    unset($data);
                    $config['definicion_diseno'] = $config['definicion_diseno'];
                }

                $item->configuracion = $config;
            }

            // Liberar relaciones cargadas que ya no se necesitan en la respuesta
            $item->unsetRelation('pteMovimiento');

            return $item;
        });

        return response()->json([
            'data'          => $items,
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
            'per_page'      => $paginated->perPage(),
            'total'         => $paginated->total(),
        ]);
    }

    public function storeBulk(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.sku' => 'required|string',
            'products.*.producto_nombre' => 'required|string',
            'products.*.cantidad' => 'required|integer|min:1',
            'products.*.pte_movimiento_id' => 'required|integer'
        ]);

        $created = [];
        $userId = Auth::id() ?? 1;

        DB::beginTransaction();
        try {
            foreach ($request->products as $prod) {
                $personalizacion = Personalizacion::create([
                    'user_id' => $userId,
                    'pte_movimiento_id' => $prod['pte_movimiento_id'],
                    'sku' => $prod['sku'],
                    'producto_nombre' => $prod['producto_nombre'],
                    'cantidad' => $prod['cantidad'],
                    'tipo' => 'embroidery',
                    'estado' => 'pending-design',
                    'prioridad' => 'medium',
                    'configuracion' => ['layers' => []]
                ]);

                $created[] = $personalizacion;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear personalizaciones masivas'], 500);
        }

        return response()->json($created, 201);
    }

    /**
     * Detalle completo de una personalización (incluye configuracion sin slimming).
     * Usado cuando el frontend abre un item para editar.
     */
    public function show($id)
    {
        $item = Personalizacion::with([
            'user:id,name,email',
            'cotizacion:id,numero,total,plazo_produccion',
            'cotizacion.cliente:id,nombres,apellidos,email,empresa',
            'pteMovimiento:id,cotizacion_id,sku,tipo_movimiento',
            'pteMovimiento.skuProducto:sku,nombre,color_id,talla_id,familia_id,subfamilia_id',
            'pteMovimiento.skuProducto.color:id,nombre',
            'pteMovimiento.skuProducto.talla:id,nombre',
            'pteMovimiento.skuProducto.familia:id,nombre',
            'pteMovimiento.skuProducto.subfamilia:id,nombre',
        ])->findOrFail($id);

        // Append computed convenience fields (same as index)
        $skuProd = $item->pteMovimiento?->skuProducto;
        $item->color      = $skuProd?->color?->nombre;
        $item->familia    = $skuProd?->familia?->nombre;
        $item->subfamilia = $skuProd?->subfamilia?->nombre;
        $item->talla      = $skuProd?->talla?->nombre;

        return response()->json($item);
    }

    /**
     * Subir un asset de diseño (imagen) al disco público.
     * POST /api/personalizaciones/assets
     *
     * Body (multipart): file, personalizacion_id
     * Respuesta: { url: "/storage/personalizaciones/{id}/filename.jpg" }
     */
    public function uploadAsset(Request $request)
    {
        $request->validate([
            'file'               => 'required|file|image|max:5120', // máx 5 MB
            'personalizacion_id' => 'required|integer|exists:personalizaciones,id',
        ]);

        $id   = $request->personalizacion_id;
        $path = $request->file('file')->store("personalizaciones/{$id}", 'public');

        return response()->json([
            'url' => '/storage/' . $path,
        ], 201);
    }

    /**
     * Crear una nueva personalización.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'nullable|string', // AHORA PUEDE SER NULLABLE SI ES MANUAL SIN INVENTARIO
            'producto_nombre' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'tipo' => 'required|string',
            'cotizacion_id' => 'nullable|exists:cotizaciones,id',
            // pte_movimiento_id es opcional
        ]);

        $personalizacion = Personalizacion::create([
            'user_id' => Auth::id() ?? 1, // Fallback si no hay auth (pero debería haber)
            'pte_movimiento_id' => $request->pte_movimiento_id,
            'cotizacion_id' => $request->cotizacion_id,
            'sku' => $request->sku ?? 'CUSTOM',
            'producto_nombre' => $request->producto_nombre,
            'cantidad' => $request->cantidad,
            'tipo' => $request->tipo,
            'estado' => 'pending-definition', // Estado inicial cambiado para el nuevo flujo
            'prioridad' => $request->prioridad ?? 'medium',
            'notas' => $request->notas,
            'configuracion' => $request->configuracion ?? [] // JSON
        ]);

        return response()->json($personalizacion, 201);
    }

    /**
     * Actualizar estado o configuración.
     */
    public function update(Request $request, $id)
    {
        $personalizacion = Personalizacion::findOrFail($id);

        $oldStatus = $personalizacion->estado;

        $personalizacion->update($request->only([
            'estado',
            'configuracion',
            'notas',
            'prioridad',
            'tipo'
        ]));

        return response()->json($personalizacion);
    }



    /**
     * Eliminar (soft delete).
     */
    public function destroy($id)
    {
        $personalizacion = Personalizacion::findOrFail($id);
        $personalizacion->delete();
        return response()->json(['message' => 'Personalización eliminada']);
    }

    /**
     * Obtener productos disponibles para personalizar (basado en salidas).
     * Busca en PteMovimiento donde tipo_movimiento = 'SALIDA_VENTA'
     */
    public function getAvailableProducts(Request $request)
    {
        // Traemos las ultimas 100 salidas que no tengan personalización aún
        $movimientos = PteMovimiento::where('tipo_movimiento', 'SALIDA_VENTA')
            ->whereDoesntHave('personalizacion') // Excluir si ya existe una ficha
            ->with(['skuProducto.familia', 'skuProducto.subfamilia', 'skuProducto.color', 'skuProducto.talla'])
            ->orderBy('fecha_hora', 'desc')
            ->take(100)
            ->get();

        // Pre-fetch colors for fallback lookup
        $colors = \App\Models\PteColor::pluck('nombre', 'id');

        // Formateamos para el frontend
        $data = $movimientos->map(function ($mov) use ($colors) {
            $sku = $mov->sku;
            $colorName = null;

            // 1. Try direct relationship
            if ($mov->skuProducto && $mov->skuProducto->color) {
                $colorName = $mov->skuProducto->color->nombre;
            }

            // 2. Fallback: Extract from SKU (Anteantepenultimo y Antepenultimo digits)
            // Example: SKU 12345678 -> Length 8. -4 and -3 are 5 and 6.
            if ((!$colorName || $colorName === 'Generico') && strlen($sku) >= 4) {
                $colorIdStr = substr($sku, -4, 2); // Get 2 chars starting at -4
                $colorId = (int) $colorIdStr;
                if (isset($colors[$colorId])) {
                    $colorName = $colors[$colorId];
                }
            }

            return [
                'id' => $mov->id, // ID del movimiento para linkear
                'sku' => $mov->sku,
                'producto_nombre' => $mov->skuProducto ? $mov->skuProducto->nombre : 'Producto Desconocido',
                'cantidad' => abs($mov->cantidad), // Cantidad vendida (es negativa en salida)
                'fecha' => $mov->fecha_hora,
                'cotizacion_id' => $mov->cotizacion_id,
                'familia' => $mov->skuProducto && $mov->skuProducto->familia ? $mov->skuProducto->familia->nombre : null,
                'subfamilia' => $mov->skuProducto && $mov->skuProducto->subfamilia ? $mov->skuProducto->subfamilia->nombre : null,
                'color' => $colorName,
                'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : null,
            ];
        });

        return response()->json($data);
    }
}
