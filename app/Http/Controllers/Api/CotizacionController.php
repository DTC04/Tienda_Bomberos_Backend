<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CotizacionUpdateRequest;
use App\Models\Cotizacion;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        $q = Cotizacion::query()->with(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos']);

        if ($request->filled('estado_id')) {
            $q->where('estado_id', (int) $request->input('estado_id'));
        }

        if ($request->filled('cliente_id')) {
            if ($request->boolean('include_children')) {
                $clienteId = (int) $request->input('cliente_id');
                $q->where(function ($query) use ($clienteId) {
                    $query->where('cliente_id', $clienteId)
                        ->orWhereHas('cliente', function ($c) use ($clienteId) {
                            $c->where('parent_id', $clienteId);
                        });
                });
            } else {
                $q->where('cliente_id', (int) $request->input('cliente_id'));
            }
        }

        if ($request->filled('oportunidad_id')) {
            $q->where('oportunidad_id', (int) $request->input('oportunidad_id'));
        }

        if ($request->filled('ejecutivo_id')) {
            $q->where('user_id', (int) $request->input('ejecutivo_id'));
        }

        if ($request->filled('from')) {
            $q->whereDate('fecha_creacion', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('fecha_creacion', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $s = '%' . $request->string('search')->toString() . '%';
            $q->where(function ($w) use ($s) {
                $w->where('numero', 'like', $s)
                    ->orWhere('id', 'like', $s)
                    ->orWhereHas(
                        'cliente',
                        fn($c) =>
                        $c->where('nombre_empresa', 'like', $s)
                            ->orWhere('nombre_contacto', 'like', $s)
                    );
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        return response()->json($q->orderByDesc('id')->paginate($perPage));
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles']);
        return response()->json($cotizacion);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'oportunidad_id' => ['nullable', 'integer', 'exists:oportunidades,id'],
            'fecha_creacion' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'origen' => ['nullable', 'string', 'max:50'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'plazo_produccion' => ['nullable', 'string', 'max:120'],
            'condiciones_pago' => ['nullable', 'string', 'max:120'],
            'despacho' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user(); // auth:sanctum
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // ✅ Estado por defecto: "Cotización Enviada" (scope=cotizacion)
        $estadoDefaultId = Estado::where('scope', 'cotizacion')
            ->where('nombre', 'Cotización Enviada')
            ->value('id');

        if (!$estadoDefaultId) {
            return response()->json([
                'message' => 'No existe el estado "Cotización Enviada" con scope=cotizacion.',
            ], 500);
        }

        $cotizacion = Cotizacion::create([
            'cliente_id' => (int) $data['cliente_id'],
            'oportunidad_id' => $data['oportunidad_id'] ?? null,
            'user_id' => $user->id,
            'estado_id' => $estadoDefaultId,
            'fecha_creacion' => $data['fecha_creacion'] ?? now()->toDateString(),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? now()->addWeekdays(15)->toDateString(),
            'observaciones' => $data['observaciones'] ?? null,
            'origen' => $data['origen'] ?? 'clientes',
            'nombre' => $data['nombre'] ?? null,
            'plazo_produccion' => $data['plazo_produccion'] ?? null,
            'condiciones_pago' => $data['condiciones_pago'] ?? null,
            'despacho' => $data['despacho'] ?? null,

            // ✅ por defecto
            'total_neto' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        // Si no se proporcionó número, generamos uno automático basado en el ID
        if (!$cotizacion->numero) {
            $cotizacion->update(['numero' => 'CT-' . str_pad($cotizacion->id, 4, '0', STR_PAD_LEFT)]);
        }

        return response()->json(
            $cotizacion->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles']),
            201
        );
    }

    /**
     * "12.597" / "50,388" / "$ 12.597" => 12597 / 50388
     */
    private function parseMoneyToInt($v): ?int
    {
        if ($v === null)
            return null;
        $digits = preg_replace('/[^\d]/', '', (string) $v);
        if ($digits === '' || $digits === null)
            return null;
        return (int) $digits;
    }

    public function update(CotizacionUpdateRequest $request, Cotizacion $cotizacion)
    {
        if (!$cotizacion->id) {
            return response()->json(['message' => 'Cotización no encontrada.'], 404);
        }

        $data = $request->validated();
        $detalles = $data['detalles'] ?? null;
        unset($data['detalles']);

        return DB::transaction(function () use ($cotizacion, $data, $detalles, $request) {
            // ✅ SIEMPRE lock y re-fetch
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            // ✅ si por algún motivo quedó null, lo rellenamos
            if (empty($cot->fecha_creacion)) {
                $cot->fecha_creacion = now()->toDateString();
            }

            // ✅ Auto-set vencimiento 15 días si no existe
            if (empty($cot->fecha_vencimiento)) {
                $cot->fecha_vencimiento = \Carbon\Carbon::parse($cot->fecha_creacion)->addWeekdays(15)->toDateString();
            }

            // ✅ Nunca permitir que cliente_id quede null (DB lo exige)
            if (array_key_exists('cliente_id', $data) && $data['cliente_id'] === null) {
                unset($data['cliente_id']);
            }

            if (!empty($data)) {
                // Unique numero manual
                if (array_key_exists('numero', $data) && $data['numero'] !== null) {
                    $exists = Cotizacion::where('numero', $data['numero'])
                        ->where('id', '!=', $cot->id)
                        ->exists();

                    if ($exists) {
                        return response()->json([
                            'message' => 'El numero de cotizacion ya existe.',
                            'errors' => ['numero' => ['El numero de cotizacion ya existe.']],
                        ], 422);
                    }
                }

                $cot->fill($data);
                $cot->save();
            } else {
                $cot->save();
            }


            if ($detalles !== null) {
                /**
                 * ✅ IMPORTANTE (SKU opcional):
                 * Este controller asume que YA cambiaste la DB para permitir:
                 * - detalle_cotizaciones.sku NULLABLE
                 * - y/o que NO exista una FK obligatoria a pte_skus(sku)
                 *
                 * Si tu tabla todavía tiene la FK, te va a reventar cuando sku no exista o sea null.
                 */

                DB::table('detalle_cotizaciones')
                    ->where('cotizacion_id', $cot->id)
                    ->delete();

                $now = now();
                $rows = [];

                $totalNetoCalc = 0;

                foreach ($detalles as $idx => $d) {
                    $cantidad = (int) ($d['cantidad'] ?? 0);

                    $precioUnitario = $this->parseMoneyToInt($d['precio_unitario'] ?? null);
                    $totalNetoItem = $this->parseMoneyToInt($d['total_neto'] ?? null);
                    $subtotalItem = $this->parseMoneyToInt($d['subtotal'] ?? null);

                    // ✅ calcula total neto (fallback si no viene total_neto)
                    if ($totalNetoItem !== null) {
                        $totalNetoCalc += $totalNetoItem;
                    } else {
                        $totalNetoCalc += (($precioUnitario ?? 0) * $cantidad);
                    }

                    $rows[] = [
                        'cotizacion_id' => $cot->id,

                        // ✅ permite cotizar sin SKU exacto
                        'sku' => $d['sku'] ?? null,

                        'n_item' => $d['n_item'] ?? ($idx + 1),
                        'cantidad' => $cantidad,
                        'subtotal' => $subtotalItem,
                        'is_personalizable' => (bool) ($d['is_personalizable'] ?? false),

                        // 👇 estos campos deben existir en tu tabla detalle_cotizaciones,
                        // si no existen, elimínalos de aquí y de tu request.
                        'producto' => $d['producto'] ?? null,
                        'talla' => $d['talla'] ?? null,
                        'color' => $d['color'] ?? null,
                        'genero' => $d['genero'] ?? null,
                        'tipo_personalizacion' => $d['tipo_personalizacion'] ?? null,

                        'precio_unitario' => $precioUnitario,
                        'total_neto' => $totalNetoItem,

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('detalle_cotizaciones')->insert($rows);
                }

                // ✅ Persistimos totales en cabecera (esto es lo que necesita el Kanban)
                $cot->total_neto = (int) $totalNetoCalc;

                // Si quieres IVA/total final automáticos:
                $cot->iva = (int) round($cot->total_neto * 0.19);
                $cot->total = (int) ($cot->total_neto + $cot->iva);

                $cot->save();
            }

            // ✅ Log historial de modificación (siempre, ya que entró al update)
            \App\Models\HistorialEtapa::create([
                'model_type' => Cotizacion::class,
                'model_id' => $cot->id,
                'user_id' => ($request->user() ? $request->user()->id : 1), // fallback si no auth
                'estado_anterior_id' => $cot->estado_id,
                'estado_nuevo_id' => $cot->estado_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(
                $cot->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles'])
            );
        });
    }

    public function destroy(Cotizacion $cotizacion)
    {
        return DB::transaction(function () use ($cotizacion) {
            DB::table('detalle_cotizaciones')->where('cotizacion_id', $cotizacion->id)->delete();
            $cotizacion->delete();

            return response()->json(['ok' => true]);
        });
    }

    public function updateEstado(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados,id'],
        ]);

        return DB::transaction(function () use ($request, $cotizacion) {
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            $estadoId = (int) $request->input('estado_id');

            $estado = Estado::find($estadoId);
            if (($estado->scope ?? null) !== 'cotizacion') {
                return response()->json([
                    'message' => 'El estado enviado no pertenece al scope cotizacion.',
                ], 422);
            }

            // Validar restricción para "Ventas" (#4 o busca por nombre)
            if ($estado->nombre === 'Aprobada por Cliente' || $estado->nombre === 'Ventas') {
                // Verificar documentos
                $count = \App\Models\CotizacionArchivo::where('cotizacion_id', $cot->id)
                    ->whereIn('tipo', ['orden_venta', 'facturacion', 'pago'])
                    ->count();

                if ($count === 0) {
                    return response()->json([
                        'message' => 'Debes adjuntar al menos un documento (Orden de Venta, Facturación o Pago) para aprobar la cotización.',
                        'code' => 'MISSING_DOCS'
                    ], 422);
                }
            }

            // ✅ Validar "Rechazada"
            if ($estado->nombre === 'Rechazada' || $estado->nombre === 'Rechazado') {
                if (!$request->filled('motivo_rechazo')) {
                    return response()->json([
                        'message' => 'Debes indicar el motivo del rechazo.',
                        'code' => 'MISSING_REASON'
                    ], 422);
                }
                $cot->motivo_rechazo = $request->input('motivo_rechazo');
                $cot->fecha_rechazo = now();
            }

            $estadoAnteriorId = $cot->estado_id;
            $cot->estado_id = $estadoId;
            $cot->save();

            // Registrar Historial
            if ($estadoAnteriorId !== $estadoId) {
                \App\Models\HistorialEtapa::create([
                    'model_type' => Cotizacion::class,
                    'model_id' => $cot->id,
                    'user_id' => $request->user()->id,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => $estadoId,
                ]);
            }

            return response()->json([
                'cotizacion' => $cot->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos']),
            ]);
        });
    }

    public function uploadArchivo(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'], // 10MB
            'tipo' => ['required', 'string', 'in:orden_venta,facturacion,pago'],
        ]);

        $file = $request->file('archivo');
        $path = $file->store('cotizaciones/' . $cotizacion->id, 'public');

        $archivo = \App\Models\CotizacionArchivo::create([
            'cotizacion_id' => $cotizacion->id,
            'tipo' => $request->input('tipo'),
            'nombre_archivo' => $file->getClientOriginalName(),
            'url' => \Illuminate\Support\Facades\Storage::url($path),
        ]);

        return response()->json($archivo, 201);
    }

    public function exportPdf(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles']);

        // Usar el ejecutivo almacenado en la cotización (no depende de Bearer token del request)
        $vendedor = $cotizacion->ejecutivo;

        // Lógica para nombre completo y comuna
        $cliente = $cotizacion->cliente;
        $parent = $cliente->parent; // Pre-loaded in load()

        // Si tiene parent (Cuerpo), usamos los datos del parent para facturación/encabezado
        $targetCliente = $parent ?? $cliente;

        $nombreClientePdf = $targetCliente->nombre_empresa ?? $targetCliente->nombre_contacto ?? 'Sin Nombre';
        $rutClientePdf = $targetCliente->rut_empresa ?? $targetCliente->rut ?? 'Sin RUT';
        $direccionClientePdf = $targetCliente->direccion ?? '---';
        $giroClientePdf = $targetCliente->giro ?? '---';

        // Comuna: intentamos sacar la provincia o region si no hay campo comuna
        // Ojo: el campo 'comuna' no existe en fillable, asumimos que podría no existir.
        // Usamos la provincia como proxy de ciudad/comuna si existe.
        $comunaClientePdf = $targetCliente->comuna ?? ($targetCliente->provincia->nombre ?? '');

        // Si es cuerpo de bomberos (CB ...), intentar expandir si el nombre es corto
        // Aplica tanto para cliente directo como para parent si viene abreviado
        if ($targetCliente && str_starts_with($nombreClientePdf, 'CB ')) {
            $parte = substr($nombreClientePdf, 3); // "Antuco"
            $nombreClientePdf = "Cuerpo de Bomberos de " . $parte;

            // Si no tiene comuna, asumimos que es la parte del nombre
            if (empty($comunaClientePdf)) {
                $comunaClientePdf = $parte;
            }
        }

        // Usamos el facade de Barryvdh\DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cotizacion', [
            'cotizacion' => $cotizacion,
            'vendedor' => $vendedor,
            'nombreClientePdf' => $nombreClientePdf,
            'rutClientePdf' => $rutClientePdf,
            'direccionClientePdf' => $direccionClientePdf,
            'giroClientePdf' => $giroClientePdf,
            'comunaClientePdf' => $comunaClientePdf,
        ]);

        $nombreArchivo = "cotizacion_{$cotizacion->id}.pdf";

        return $pdf->stream($nombreArchivo);
    }

    public function storeGestion(Request $request, Cotizacion $cotizacion)
    {
        $data = $request->validate([
            'glosa' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento_nueva' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($request, $cotizacion, $data) {
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            // Crear registro de gestión
            \App\Models\CotizacionGestion::create([
                'cotizacion_id' => $cot->id,
                'user_id' => $request->user() ? $request->user()->id : 1,
                'glosa' => $data['glosa'] ?? '',
                'fecha_gestion' => now(),
                'fecha_vencimiento_nueva' => $data['fecha_vencimiento_nueva'],
            ]);

            // Actualizar fecha de vencimiento de la cotización
            $cot->fecha_vencimiento = $data['fecha_vencimiento_nueva'];
            $cot->save();

            return response()->json(
                $cot->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion'])
            );
        });
    }

    public function marcarPersonalizacionCompletada(Request $request, Cotizacion $cotizacion)
    {
        $cotizacion->personalizacion_completada = true;
        $cotizacion->save();

        return response()->json([
            'ok' => true,
            'cotizacion' => $cotizacion->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos'])
        ]);
    }
}
