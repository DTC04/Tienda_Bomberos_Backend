<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuttingOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CuttingOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $orders = CuttingOrder::with(['items' => function($query) {
                    $query->withSum('packages as packaged_quantity', 'items_paquetes.quantity');
                }, 'supplies'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes de corte: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        \Log::info('Creating cutting order with data:', $request->all());
        
        // Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'client' => 'string|nullable',
            'selected_product' => 'required|string',
            'notes' => 'string|nullable',
            'estimated_days' => 'integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.product_type' => 'required|string',
            'items.*.size' => 'required|string',
            'items.*.color' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.fabric_type' => 'required|string',
            'items.*.notes' => 'string|nullable',
            'supplies' => 'array',
            'supplies.*.name' => 'required|string',
            'supplies.*.type' => 'required|string',
            'supplies.*.quantity' => 'required|numeric|min:0',
            'supplies.*.unit' => 'required|string',
            'supplies.*.notes' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generar el próximo código automáticamente
            $nextCode = $this->generateNextCode();
            \Log::info('Generated next code:', ['code' => $nextCode]);

            // Crear la orden de corte con el código generado
            $order = CuttingOrder::create([
                'code' => $nextCode,
                'client' => $request->client ?? 'Orden de Producción Interna',
                'selected_product' => $request->selected_product,
                'status' => 'order-received',
                'notes' => $request->notes,
                'estimated_days' => $request->estimated_days ?? 7,
                'progress' => 0
            ]);
            
            \Log::info('Order created:', ['order_id' => $order->id, 'code' => $order->code]);

            // Crear los items de la orden
            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_type' => $item['product_type'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'quantity' => $item['quantity'],
                    'fabric_type' => $item['fabric_type'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            // Crear los insumos si se proporcionan
            if (!empty($request->supplies)) {
                foreach ($request->supplies as $supply) {
                    $order->supplies()->create([
                        'name' => $supply['name'],
                        'type' => $supply['type'],
                        'quantity' => $supply['quantity'],
                        'unit' => $supply['unit'],
                        'notes' => $supply['notes'] ?? null
                    ]);
                }
            }

            // LOGGING
            \App\Models\FactoryLog::create([
                'action' => 'Orden de Corte Creada',
                'description' => "Se creó la orden {$order->code} para {$order->client}",
                'details' => json_encode($order->toArray()),
                'entity_type' => 'CuttingOrder',
                'entity_id' => $order->id,
                'user_id' => $request->user()?->id
            ]);

            DB::commit();

            // Cargar las relaciones para la respuesta
            $order->load(['items', 'supplies']);

            return response()->json([
                'success' => true,
                'message' => 'Orden de corte creada exitosamente',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden de corte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $order = CuttingOrder::with(['items', 'supplies'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $order = CuttingOrder::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'string|in:order-received,cutting,cutting-finished,transit,sewing,quality-control,finished',
                'progress' => 'numeric|min:0|max:100',
                'notes' => 'string|nullable',
                'estimated_days' => 'integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $order->status;

            // Validación de Control de Calidad Parcial
            // 1. No se puede mover a 'Terminado' si faltan unidades por procesar
            if ($request->has('status') && $request->status === 'finished' && $oldStatus !== 'finished') {
                $validationResult = $this->validateFinishedTransition($order);
                if ($validationResult) {
                    return response()->json($validationResult, 422);
                }
            }

            // 2. No se puede salir de 'Control de Calidad' si faltan unidades por procesar
            if ($request->has('status')) {
                $validationResult = $this->validateQualityControlTransition($order, $request->status);
                if ($validationResult) {
                    return response()->json($validationResult, 422);
                }
            }

            $order->update($request->only(['status', 'progress', 'notes', 'estimated_days']));

            // LOGGING
            $statusMap = [
                'order-received' => 'Corte',
                'cutting' => 'Corte',
                'cutting-finished' => 'Corte Terminado',
                'transit' => 'Tránsito',
                'sewing' => 'Confección',
                'quality-control' => 'Control de Calidad',
                'finished' => 'Terminado'
            ];

            if ($request->has('status') && $request->status !== $oldStatus) {
                $statusLabel = $statusMap[$request->status] ?? $request->status;
                \App\Models\FactoryLog::create([
                    'action' => 'Cambio de Estado',
                    'description' => "La orden {$order->code} cambió de estado a {$statusLabel}",
                    'details' => ['old_status' => $oldStatus, 'new_status' => $request->status],
                    'entity_type' => 'CuttingOrder',
                    'entity_id' => $order->id,
                    'user_id' => $request->user()?->id
                ]);
            } else {
                 \App\Models\FactoryLog::create([
                    'action' => 'Orden Actualizada',
                    'description' => "La orden {$order->code} fue actualizada",
                    'details' => $request->all(),
                    'entity_type' => 'CuttingOrder',
                    'entity_id' => $order->id,
                    'user_id' => $request->user()?->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden de corte actualizada exitosamente',
                'data' => $order->load(['items', 'supplies'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la orden de corte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update partial QC units
     */
    public function updatePartialQc(Request $request, string $id): JsonResponse
    {
        try {
            $order = CuttingOrder::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'unidades_listas' => 'required|integer|min:0',
                'unidades_en_reparacion' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de control de calidad inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order->update([
                'unidades_listas' => $request->unidades_listas,
                'unidades_en_reparacion' => $request->unidades_en_reparacion,
            ]);

            \App\Models\FactoryLog::create([
                'action' => 'Registro de Progreso QC',
                'description' => "Se registraron {$request->unidades_listas} unidades listas y {$request->unidades_en_reparacion} a reparación para la orden {$order->code}",
                'details' => $order->toArray(),
                'entity_type' => 'CuttingOrder',
                'entity_id' => $order->id,
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progreso actualizado exitosamente',
                'data' => $order->load(['items', 'supplies'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las unidades QC de la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $order = CuttingOrder::findOrFail($id);
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Orden de corte eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la orden de corte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el próximo código de orden disponible
     */
    public function getNextCode(): JsonResponse
    {
        try {
            $nextCode = $this->generateNextCode();

            return response()->json([
                'success' => true,
                'data' => ['next_code' => $nextCode]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el código',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método privado para generar el próximo código de orden
     */
    private function generateNextCode(): string
    {
        // Obtener todos los códigos que sean numéricos y ordenarlos correctamente
        $lastOrder = CuttingOrder::whereRaw('code REGEXP "^[0-9]+$"')
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->first();
        
        if (!$lastOrder) {
            return '1000';
        }
        
        $lastCodeNumber = (int) $lastOrder->code;
        
        // Asegurarse de que el próximo código sea al menos 1000
        if ($lastCodeNumber < 1000) {
            return '1000';
        }
        
        return (string) ($lastCodeNumber + 1);
    }

    /**
     * Valida si una orden puede cambiar de estado desde Control de Calidad
     * 
     * @param CuttingOrder $order La orden a validar
     * @param string $newStatus El nuevo estado al que se quiere cambiar
     * @return array|null Retorna un array con el error si no es válido, o null si es válido
     */
    private function validateQualityControlTransition(CuttingOrder $order, string $newStatus): ?array
    {
        // Si la orden NO está en control de calidad, no hay restricción
        if ($order->status !== 'quality-control') {
            return null;
        }

        // Si se intenta cambiar a un estado diferente de quality-control
        if ($newStatus !== 'quality-control') {
            $unidadesCompletas = $order->unidades_listas >= $order->total_unidades;
            $hayReparaciones = $order->unidades_en_reparacion > 0;
            $hayEnTaller = $order->unidades_en_taller > 0;
            
            // Verificar si todas las unidades están listas Y no hay unidades en reparación
            if (!$unidadesCompletas || $hayReparaciones || $hayEnTaller) {
                $unidadesFaltantes = $order->total_unidades - $order->unidades_listas;
                $porcentajeCompletado = $order->total_unidades > 0 
                    ? round(($order->unidades_listas / $order->total_unidades) * 100, 1) 
                    : 0;

                $mensajes = [
                    'La orden debe permanecer en Control de Calidad hasta completar todas las unidades.',
                ];

                if ($unidadesFaltantes > 0) {
                    $mensajes[] = "Faltan {$unidadesFaltantes} unidades por procesar ({$porcentajeCompletado}% completado).";
                }

                if ($hayReparaciones) {
                    $mensajes[] = "Hay {$order->unidades_en_reparacion} unidades en reparación que deben ser procesadas.";
                }

                if ($hayEnTaller) {
                    $mensajes[] = "Hay {$order->unidades_en_taller} unidades aún en el taller.";
                }

                $mensajes[] = "Progreso actual: {$order->unidades_listas} listas / {$order->unidades_en_reparacion} en reparación / {$order->unidades_en_taller} en taller (Total: {$order->total_unidades})";
                $mensajes[] = "Las unidades deben ser registradas mediante entregas parciales del taller.";

                return [
                    'success' => false,
                    'message' => 'Control de Calidad Incompleto',
                    'errors' => [
                        'status' => $mensajes
                    ]
                ];
            }
        }

        return null;
    }

    /**
     * Valida si una orden puede moverse al estado 'Terminado'
     * 
     * @param CuttingOrder $order La orden a validar
     * @return array|null Retorna un array con el error si no es válido, o null si es válido
     */
    private function validateFinishedTransition(CuttingOrder $order): ?array
    {
        $unidadesCompletas = $order->unidades_listas >= $order->total_unidades;
        $hayReparaciones = $order->unidades_en_reparacion > 0;
        $hayEnTaller = $order->unidades_en_taller > 0;

        // La orden solo puede finalizar si:
        // 1. Todas las unidades están listas (unidades_listas >= total_unidades)
        // 2. NO hay unidades en reparación
        // 3. NO hay unidades en taller
        if (!$unidadesCompletas || $hayReparaciones || $hayEnTaller) {
            $unidadesFaltantes = $order->total_unidades - $order->unidades_listas;
            $porcentajeCompletado = $order->total_unidades > 0 
                ? round(($order->unidades_listas / $order->total_unidades) * 100, 1) 
                : 0;

            $mensajes = [
                'No se puede mover la orden a Terminado. Todos los ítems deben pasar exitosamente el Control de Calidad.',
            ];

            if ($unidadesFaltantes > 0) {
                $mensajes[] = "Faltan {$unidadesFaltantes} unidades por procesar ({$porcentajeCompletado}% completado).";
            }

            if ($hayReparaciones) {
                $mensajes[] = "⚠️ Hay {$order->unidades_en_reparacion} unidades en reparación que deben completarse primero.";
            }

            if ($hayEnTaller) {
                $mensajes[] = "⚠️ Hay {$order->unidades_en_taller} unidades aún en el taller esperando inspección.";
            }

            $mensajes[] = "Estado actual: {$order->unidades_listas} listas / {$order->unidades_en_reparacion} en reparación / {$order->unidades_en_taller} en taller (Total: {$order->total_unidades})";

            return [
                'success' => false,
                'message' => 'Operación Denegada',
                'errors' => [
                    'status' => $mensajes
                ]
            ];
        }

        return null;
    }
}
