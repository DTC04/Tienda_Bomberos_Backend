<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Peticion;
use App\Models\PteStock;
use App\Models\PteMovimiento;
use App\Models\Estado;
use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class OrderRequestController extends Controller
{
    /**
     * List quotes available for reservation (Sent or Approved).
     */
    public function quotes(Request $request)
    {
        $validStatusIds = Estado::where('scope', 'cotizacion')
            ->whereIn('nombre', ['Cotización Enviada', 'Ventas'])
            ->pluck('id');

        $query = Cotizacion::whereIn('estado_id', $validStatusIds)
            ->with('cliente')
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('numero', 'like', $s)
                    ->orWhere('id', 'like', $s)
                    ->orWhereHas('cliente', function ($c) use ($s) {
                        $c->where('nombre_empresa', 'like', $s);
                    });
            });
        }

        return response()->json($query->limit(50)->get());
    }

    /**
     * Create a new stock reservation request.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|exists:pte_skus,sku',
            'items.*.quantity' => 'required|integer|min:1',
            'quote_identifier' => 'required', // Can be ID (numeric) or Number (string)
            'observation' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $quoteIdentifier = $request->quote_identifier;

            // Flexible lookup for Cotizacion
            $cotizacion = Cotizacion::where('id', $quoteIdentifier)
                ->orWhere('numero', $quoteIdentifier)
                ->first();

            if (!$cotizacion) {
                return response()->json([
                    'message' => "Cotización no encontrada: {$quoteIdentifier}"
                ], 404);
            }

            $createdRequests = [];

            foreach ($request->items as $item) {
                $sku = $item['sku'];
                $quantity = $item['quantity'];

                $stock = PteStock::where('sku', $sku)->first();

                if (!$stock || $stock->cantidad < $quantity) {
                    throw new \Exception("Stock físico insuficiente para el SKU {$sku}.");
                }

                // Guardar stock anterior para registrar el movimiento
                $prevStock = $stock->cantidad;

                // NUEVO: Descontar stock físico inmediatamente al reservar
                $stock->cantidad -= $quantity;
                // Incrementar stock reservado (para mostrar en columna de reservados)
                $stock->reserved_stock += $quantity;
                $stock->save();

                // NUEVO: Registrar movimiento en bitácora como RESERVA_PRODUCTO
                PteMovimiento::create([
                    'sku' => $sku,
                    'tipo_movimiento' => 'RESERVA_PRODUCTO',
                    'cantidad' => $quantity,
                    'saldo_anterior' => $prevStock,
                    'saldo_nuevo' => $stock->cantidad,
                    'fecha_hora' => now(),
                    'usuario_id' => auth()->id(),
                    'cotizacion_id' => $cotizacion->id,
                    'id_lote' => (string) Str::uuid(),
                    'observacion' => "Reserva de producto para Cotización #{$cotizacion->id}"
                ]);

                // Asegurar que el estado exista (por si el seeder no se corrió)
                $pendingStatus = Estado::firstOrCreate(
                    ['scope' => 'peticion', 'nombre' => 'Pendiente'],
                    ['color' => 'blue', 'orden' => 1]
                );
                $pendingStatusId = $pendingStatus->id;

                $orderRequest = Peticion::create([
                    'sku' => $sku,
                    'cantidad' => $quantity,
                    'cotizacion_id' => $cotizacion->id,
                    'estado_id' => $pendingStatusId,
                    'user_id' => auth()->id(),
                    'fecha_creacion' => now()->toDateString(),
                    'observacion' => $request->observation
                ]);

                $createdRequests[] = $orderRequest;
            }

            return response()->json([
                'message' => 'Solicitud de pedido creada y stock reservado con éxito.',
                'data' => $createdRequests
            ], 201);
        });
    }

    /**
     * Get just the count of pending order requests (for performance on frontend)
     */
    public function countPending()
    {
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        if (!$pendingStatusId) {
            return response()->json(['count' => 0]);
        }

        $count = Peticion::where('estado_id', $pendingStatusId)->count();

        return response()->json(['count' => $count]);
    }

    /**
     * List pending order requests.
     */
    public function index()
    {
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        $requests = Peticion::where('estado_id', $pendingStatusId)
            ->with(['skuProducto.talla', 'cotizacion.cliente', 'estado', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }

    /**
     * Confirm/Approve a dispatch request.
     */
    public function confirm(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $orderRequest = Peticion::findOrFail($id);
                $this->processConfirmation($orderRequest);

                return response()->json([
                    'message' => 'Despacho confirmado y stock actualizado.',
                    'data' => $orderRequest->load('estado')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Confirm/Approve multiple dispatch requests at once.
     */
    public function bulkConfirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:peticiones,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'IDs inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $confirmed = [];
                foreach ($request->ids as $id) {
                    $orderRequest = Peticion::findOrFail($id);

                    // Skip if already processed to avoid errors in bulk
                    if ($orderRequest->estado->nombre !== 'Pendiente') {
                        continue;
                    }

                    $this->processConfirmation($orderRequest);
                    $confirmed[] = $orderRequest->id;
                }

                return response()->json([
                    'message' => count($confirmed) . ' despachos confirmados con éxito.',
                    'confirmed_ids' => $confirmed
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Core logic to confirm a single request and update stock.
     */
    private function processConfirmation(Peticion $orderRequest)
    {
        if ($orderRequest->estado->nombre !== 'Pendiente') {
            throw new \Exception("La solicitud #{$orderRequest->id} ya ha sido procesada.");
        }

        $stock = PteStock::where('sku', $orderRequest->sku)->first();

        if (!$stock) {
            throw new \Exception("Stock no encontrado para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // Verificar que haya suficiente reservado para liberar
        if ($stock->reserved_stock < $orderRequest->cantidad) {
            throw new \Exception("Error de consistencia: Stock reservado insuficiente para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // NO DESCONTAMOS stock físico (ya se descontó al reservar)
        // Solo liberamos el stock reservado
        $prevStock = $stock->cantidad;
        $stock->reserved_stock -= $orderRequest->cantidad;
        $stock->save();

        // Update request status
        $despachadoStatus = Estado::firstOrCreate(
            ['scope' => 'peticion', 'nombre' => 'Despachado'],
            ['color' => 'green', 'orden' => 2]
        );
        $orderRequest->estado_id = $despachadoStatus->id;
        $orderRequest->save();

        // Record movement (el saldo no cambia porque ya se descontó al reservar)
        PteMovimiento::create([
            'sku' => $orderRequest->sku,
            'tipo_movimiento' => 'SALIDA_VENTA',
            'cantidad' => $orderRequest->cantidad,
            'saldo_anterior' => $prevStock,
            'saldo_nuevo' => $prevStock, // El saldo no cambia (ya se descontó en la reserva)
            'fecha_hora' => now(),
            'usuario_id' => auth()->id(),
            'cotizacion_id' => $orderRequest->cotizacion_id,
            'id_lote' => (string) Str::uuid(),
            'observacion' => "Confirmación de despacho para Solicitud #{$orderRequest->id} (Cotización #{$orderRequest->cotizacion_id})"
        ]);
    }

    /**
     * Reject/Cancel a dispatch request.
     */
    public function reject(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $orderRequest = Peticion::findOrFail($id);
                $this->processRejection($orderRequest);

                return response()->json([
                    'message' => 'Despacho rechazado y stock liberado.',
                    'data' => $orderRequest->load('estado')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Reject multiple dispatch requests at once.
     */
    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:peticiones,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'IDs inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $rejected = [];
                foreach ($request->ids as $id) {
                    $orderRequest = Peticion::findOrFail($id);

                    if ($orderRequest->estado->nombre !== 'Pendiente') {
                        continue;
                    }

                    $this->processRejection($orderRequest);
                    $rejected[] = $orderRequest->id;
                }

                return response()->json([
                    'message' => count($rejected) . ' despachos rechazados con éxito.',
                    'rejected_ids' => $rejected
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Core logic to reject a single request and revert stock.
     */
    private function processRejection(Peticion $orderRequest)
    {
        if ($orderRequest->estado->nombre !== 'Pendiente') {
            throw new \Exception("La solicitud #{$orderRequest->id} ya ha sido procesada.");
        }

        $stock = PteStock::where('sku', $orderRequest->sku)->first();

        if (!$stock) {
            throw new \Exception("Stock no encontrado para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        if ($stock->reserved_stock < $orderRequest->cantidad) {
            throw new \Exception("Error de consistencia: Stock reservado insuficiente para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // Devolver stock físico y liberar stock reservado
        $prevStock = $stock->cantidad; // stock real activo circulante
        $stock->reserved_stock -= $orderRequest->cantidad;
        $stock->cantidad += $orderRequest->cantidad;
        $stock->save();

        // Update request status
        $rechazadoStatus = Estado::firstOrCreate(
            ['scope' => 'peticion', 'nombre' => 'Rechazado'],
            ['color' => 'red', 'orden' => 3]
        );
        $orderRequest->estado_id = $rechazadoStatus->id;
        $orderRequest->save();

        // Record movement
        PteMovimiento::create([
            'sku' => $orderRequest->sku,
            'tipo_movimiento' => 'ANULACION_RESERVA',
            'cantidad' => $orderRequest->cantidad,
            'saldo_anterior' => $prevStock,
            'saldo_nuevo' => $stock->cantidad, // ahora tiene más cantidad
            'fecha_hora' => now(),
            'usuario_id' => auth()->id(),
            'cotizacion_id' => $orderRequest->cotizacion_id,
            'id_lote' => (string) Str::uuid(),
            'observacion' => "Rechazo de despacho para Solicitud #{$orderRequest->id} (Cotización #{$orderRequest->cotizacion_id})"
        ]);
    }


    /**
     * Generate PDF for the initial request.
     */
    public function downloadRequestPdf($id)
    {
        $orderRequest = Peticion::with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'estado', 'user'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.order_request', [
            'order' => $orderRequest,
            'title' => 'Solicitud de Reserva de Stock',
            'ejecutivo' => $orderRequest->user->name ?? 'N/A'
        ]);

        return $pdf->stream("request_{$orderRequest->id}.pdf");
    }

    /**
     * Generate a consolidated PDF for all pending items in a quote.
     */
    public function downloadQuoteRequestPdf($cotizacionId)
    {
        $cotizacion = Cotizacion::with('cliente')->findOrFail($cotizacionId);

        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        $requests = Peticion::where('cotizacion_id', $cotizacionId)
            ->where('estado_id', $pendingStatusId)
            ->with(['skuProducto.talla', 'skuProducto.color', 'user'])
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['message' => 'No hay solicitudes pendientes para esta cotización.'], 404);
        }

        $pdf = Pdf::loadView('pdf.quote_request', [
            'cotizacion' => $cotizacion,
            'items' => $requests,
            'title' => 'Solicitud Consolidada de Reserva',
            'ejecutivo' => $requests->first()->user->name ?? 'N/A'
        ]);

        return $pdf->stream("solicitud_cotizacion_{$cotizacion->id}.pdf");
    }

    /**
     * Generate PDF for the confirmed dispatch.
     */
    public function downloadDispatchPdf($id)
    {
        $orderRequest = Peticion::with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'estado'])->findOrFail($id);

        if ($orderRequest->estado->nombre !== 'Aceptado') {
            return response()->json(['message' => 'El pedido aún no ha sido aceptado.'], 400);
        }

        $pdf = Pdf::loadView('pdf.order_request', [
            'order' => $orderRequest,
            'title' => 'Confirmación de Despacho',
            'ejecutivo' => auth()->user()->name ?? 'N/A'
        ]);

        return $pdf->stream("dispatch_{$orderRequest->id}.pdf");
    }
}
