<?php

namespace App\Http\Controllers;

use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\PteMovimiento; // <--- VITAL PARA EL HISTORIAL
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; // <--- VITAL PARA SABER QUIÉN FUE
use Illuminate\Support\Str; // <--- AGREGADO: Para generar UUIDs
use Exception;

class SkuController extends Controller
{
    /**
     * Listar todos los productos con sus relaciones (JSON puro de la BD).
     */
    public function index()
    {
        $productos = PteSku::with([
            'unidadNegocio',
            'origen',
            'grpFamilia',
            'familia',
            'subfamilia',
            'familiaTipo',
            'familiaFormato',
            'genero',
            'color',
            'talla',
            'stock'
        ])->get();

        return response()->json($productos);
    }

    /**
     * Crear un nuevo producto (Manual).
     */
    public function store(Request $request)
    {
        // 1. Validaciones básicas de formulario
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150',
            'unidad_negocio_id' => 'required',
            // ... resto de validaciones si necesitas ...
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // A. Obtener el SKU que intentamos crear
            $skuGenerado = $request->sku;

            if (!$skuGenerado) {
                return response()->json(['message' => 'El SKU es requerido'], 400);
            }

            // -------------------------------------------------------
            // --- VALIDACIÓN DE DUPLICADOS (NUEVO) ---
            // -------------------------------------------------------
            if (PteSku::where('sku', $skuGenerado)->exists()) {
                DB::rollBack();
                return response()->json([
                    'message' => "¡Atención! El SKU {$skuGenerado} ya existe en el inventario. No se pueden crear duplicados."
                ], 422);
            }
            // -------------------------------------------------------

            // B. Crear el Producto (SKU)
            $producto = new PteSku();
            $producto->sku = $skuGenerado;
            $producto->nombre = $request->nombre;
            $producto->descripcion = $request->descripcion;
            $producto->precio_venta = $request->precio_venta;

            $producto->unidad_negocio_id = $request->unidad_negocio_id;
            $producto->origen_id = $request->origen_id;
            $producto->grp_familia_id = $request->grp_familia_id;
            $producto->familia_id = $request->familia_id;
            $producto->subfamilia_id = $request->subfamilia_id;
            $producto->familia_tipo_id = $request->familia_tipo_id;
            $producto->familia_formato_id = $request->familia_formato_id;
            $producto->genero_id = $request->genero_id;
            $producto->color_id = $request->color_id;
            $producto->talla_id = $request->talla_id;

            $producto->save();

            // C. Crear el Stock Inicial
            PteStock::create([
                'sku' => $skuGenerado,
                'cantidad' => $request->cantidad ?? 0,
                'stock_critico' => $request->stock_critico ?? 5
            ]);

            // D. Registrar movimiento de "Inventario Inicial"
            if ($request->cantidad > 0) {
                PteMovimiento::create([
                    'sku' => $skuGenerado,
                    'fecha_hora' => now(),
                    'tipo_movimiento' => 'INVENTARIO_INICIAL',
                    'usuario_id' => Auth::id(),
                    'cantidad' => $request->cantidad,
                    'saldo_anterior' => 0,
                    'saldo_nuevo' => $request->cantidad,
                    // Generamos un ID de lote único también para la creación inicial
                    'id_lote' => (string) Str::uuid()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Producto creado con éxito', 'data' => $producto], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ver un producto específico.
     */
    public function show($sku)
    {
        $producto = PteSku::with(['stock'])->where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto);
    }

    /**
     * ACTUALIZAR PRODUCTO
     * Aquí está la lógica del historial de ajustes manuales e ID de Lote.
     */
    public function update(Request $request, $id)
    {
        // Nota: $id en la ruta es el SKU string
        $sku = $id;

        $producto = PteSku::where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Obtener stock actual (o 0 si no existe registro)
        $stockModel = $producto->stock;
        $stockAnterior = $stockModel ? (int) $stockModel->cantidad : 0;
        $nuevoStock = (int) $request->cantidad;

        DB::beginTransaction();

        try {
            // 1. DETECCIÓN DE CAMBIO DE STOCK (HISTORIAL)
            if ($stockAnterior !== $nuevoStock) {

                $diferencia = abs($nuevoStock - $stockAnterior);

                PteMovimiento::create([
                    'sku' => $sku,
                    'fecha_hora' => now(),
                    'tipo_movimiento' => 'AJUSTE_MANUAL',
                    'usuario_id' => Auth::id(),
                    'cantidad' => $diferencia,
                    'saldo_anterior' => $stockAnterior,
                    'saldo_nuevo' => $nuevoStock,

                    // --- AQUÍ ESTÁ LA MAGIA DEL BATCH ID ---
                    // Recibe el ID del frontend (bulk) o genera uno nuevo (individual)
                    'id_lote' => $request->input('id_lote') ?? (string) Str::uuid(),
                ]);
            }

            // 2. Actualizar datos del SKU (Nombre, Precio, etc.)
            $producto->update([
                'nombre' => $request->nombre,
                'precio_venta' => $request->precio_venta,
                'descripcion' => $request->descripcion,
            ]);

            // 3. Actualizar la tabla de Stocks
            if ($stockModel) {
                $stockModel->update([
                    'cantidad' => $nuevoStock,
                    'stock_critico' => $request->stock_critico
                ]);
            } else {
                PteStock::create([
                    'sku' => $sku,
                    'cantidad' => $nuevoStock,
                    'stock_critico' => $request->stock_critico
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Producto actualizado correctamente']);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar producto.
     */
    public function destroy($id)
    {
        $sku = $id;
        $producto = PteSku::where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // 1. BLOQUEAR si tiene historial de movimientos
        $tieneMovimientos = PteMovimiento::where('sku', $sku)->exists();
        if ($tieneMovimientos) {
            return response()->json([
                'message' => 'Este producto tiene historial de movimientos registrados y no puede eliminarse para preservar la integridad de la bitácora. Puedes desactivarlo para ocultarlo del inventario activo.',
                'can_deactivate' => true
            ], 409);
        }

        // 2. BLOQUEAR si tiene reservas (peticiones) pendientes
        $tienePeticionesActivas = \App\Models\Peticion::where('sku', $sku)
            ->whereHas('estado', fn($q) => $q->where('nombre', 'Pendiente'))
            ->exists();
        if ($tienePeticionesActivas) {
            return response()->json([
                'message' => 'Este producto tiene reservas pendientes de despacho. No se puede eliminar hasta que sean procesadas.',
                'can_deactivate' => false
            ], 409);
        }

        // 3. ELIMINAR de forma segura dentro de una transacción
        try {
            DB::transaction(function () use ($sku, $producto) {
                PteStock::where('sku', $sku)->delete();
                $producto->delete();
            });

            return response()->json(['message' => 'Producto eliminado correctamente.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Desactivar un producto (Soft-delete lógico).
     * Preserva el historial completo, solo lo oculta del inventario activo.
     */
    public function deactivate($id)
    {
        $sku = $id;
        $producto = PteSku::where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->update(['activo' => false]);

        return response()->json(['message' => "Producto '{$producto->nombre}' desactivado. El historial de movimientos se ha preservado."]);
    }

    /**
     * Endpoint especial para la Tabla de Frontend (formato aplanado).
     */
    /**
     * Endpoint especial para la Tabla de Frontend (formato aplanado).
     * Soporta Paginación, Búsqueda y Ordenamiento.
     */
    public function inventario(Request $request)
    {
        $bodegaNombre = "Bodega Central PTE";
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'producto'); // Default sort by Product + Size
        $order = $request->input('order', 'asc');

        // Construimos la Query
        // Hacemos LEFT JOIN con stocks, tallas y colores para poder ordenar
        $query = PteSku::with(['stock', 'familia', 'subfamilia', 'talla', 'color', 'genero'])
            ->select('pte_skus.*') // Importante para no sobreescribir IDs
            ->where('pte_skus.activo', true)  // Solo productos activos
            ->leftJoin('pte_stocks', 'pte_skus.sku', '=', 'pte_stocks.sku')
            ->leftJoin('pte_tallas', 'pte_skus.talla_id', '=', 'pte_tallas.id')
            ->leftJoin('pte_colores', 'pte_skus.color_id', '=', 'pte_colores.id');

        // 1. Filtrado
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pte_skus.sku', 'like', "%{$search}%")
                    ->orWhere('pte_skus.nombre', 'like', "%{$search}%");
            });
        }

        // --- FILTRO DE STOCK (NUEVO) ---
        $stockFilter = $request->input('stock_filter');
        if ($stockFilter) {
            switch ($stockFilter) {
                case 'agotados':
                    $query->where('pte_stocks.cantidad', '<=', 0);
                    break;
                case 'criticos':
                    $query->whereRaw('pte_stocks.cantidad <= pte_stocks.stock_critico')
                        ->where('pte_stocks.cantidad', '>', 0);
                    break;
                case 'bajo_stock':
                    $query->whereRaw('pte_stocks.cantidad <= (pte_stocks.stock_critico * 2)')
                        ->whereRaw('pte_stocks.cantidad > pte_stocks.stock_critico');
                    break;
            }
        }
        // -------------------------------

        // 2. Ordenamiento
        switch ($sortBy) {
            case 'cantidad_fisica':
                $query->orderBy('pte_stocks.cantidad', $order);
                break;
            case 'talla':
                $query->orderBy('pte_tallas.orden', $order);
                break;
            case 'color':
                $query->orderBy('pte_colores.nombre', $order);
                break;
            case 'precio_venta':
                $query->orderBy('pte_skus.precio_venta', $order);
                break;
            case 'producto':
                $query->orderBy('pte_skus.nombre', $order)
                    ->orderBy('pte_tallas.orden', $order);
                break;
            default:
                $query->orderBy('pte_skus.sku', $order);
                break;
        }

        // 3. Ejecutar Paginación
        $paginator = $query->paginate($perPage);

        // 5. Calcular Stats Globales (independiente de la paginación actual)
        $totalSkus = PteSku::count();
        $agotados = PteStock::where('cantidad', '<=', 0)->count();
        $criticos = PteStock::whereRaw('cantidad <= stock_critico')->where('cantidad', '>', 0)->count();
        $bajoStock = PteStock::whereRaw('cantidad <= (stock_critico * 2)')->whereRaw('cantidad > stock_critico')->count();

        $stats = [
            'total_skus' => $totalSkus,
            'agotados' => $agotados,
            'criticos' => $criticos,
            'bajo_stock' => $bajoStock
        ];

        // 6. Transformar los datos (Aplanar estructura)
        $itemsTransformados = $paginator->getCollection()->map(function ($producto) use ($bodegaNombre) {

            $cantidad = $producto->stock ? $producto->stock->cantidad : 0;
            $reservado = $producto->stock ? $producto->stock->reserved_stock : 0;
            $critico = $producto->stock ? $producto->stock->stock_critico : 5;

            return [
                'sku' => $producto->sku,
                'producto' => $producto->nombre,
                'precio_venta' => $producto->precio_venta,
                'genero' => $producto->genero ? $producto->genero->nombre : 'Unisex',
                'categoria' => $producto->subfamilia ? $producto->subfamilia->nombre : 'General',
                'talla' => $producto->talla ? $producto->talla->nombre : '-',
                'color' => $producto->color ? $producto->color->nombre : '-',
                'bodega' => $bodegaNombre,
                'cantidad_fisica' => $cantidad,
                'reserved_stock' => $reservado,
                'stock_critico' => $critico,
                'estado' => $cantidad > 0 ? 'Disponible' : 'Sin Stock'
            ];
        });

        // 7. Retornar respuesta custom
        // Convertimos el paginador a array para tener la estructura standard de Laravel (current_page, data, etc.)
        // Y le adjuntamos nuestros stats custom.
        $paginationData = $paginator->toArray();
        $paginationData['data'] = $itemsTransformados; // Reemplazamos la data cruda con la transformada

        return response()->json([
            'pagination' => $paginationData,
            'stats' => $stats
        ]);
    }

    public function reporteInventario(Request $request)
    {
        $bodegaNombre = "Bodega Central PTE";
        $search = $request->input('search');

        // Construimos la Query optimizada
        $query = PteSku::select(['sku', 'nombre', 'precio_venta', 'subfamilia_id', 'talla_id', 'color_id', 'genero_id'])
            ->where('activo', true)  // Solo productos activos
            ->with([
                'stock:sku,cantidad,reserved_stock,stock_critico',
                'subfamilia:id,nombre',
                'talla:id,nombre',
                'color:id,nombre',
                'genero:id,nombre'
            ]);

        // Filtrado si aplica
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        $productos = $query->get();

        // Transformamos (mismo formato aplanado que 'inventario')
        $items = $productos->map(function ($producto) use ($bodegaNombre) {
            $cantidad = $producto->stock ? $producto->stock->cantidad : 0;
            $reservado = $producto->stock ? $producto->stock->reserved_stock : 0;
            $critico = $producto->stock ? $producto->stock->stock_critico : 5;

            return [
                'sku' => $producto->sku,
                'producto' => $producto->nombre,
                'precio_venta' => $producto->precio_venta,
                'genero' => $producto->genero ? $producto->genero->nombre : 'Unisex',
                'categoria' => $producto->subfamilia ? $producto->subfamilia->nombre : 'General',
                'talla' => $producto->talla ? $producto->talla->nombre : '-',
                'color' => $producto->color ? $producto->color->nombre : '-',
                'bodega' => $bodegaNombre,
                'cantidad_fisica' => $cantidad,
                'reserved_stock' => $reservado,
                'stock_critico' => $critico,
                'estado' => $cantidad > 0 ? 'Disponible' : 'Sin Stock'
            ];
        });

        return response()->json($items);
    }
}