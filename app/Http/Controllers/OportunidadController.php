<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\Cotizacion;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OportunidadController extends Controller
{
    /**
     * GET /api/oportunidades
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Oportunidad::query()
            ->with(['cliente', 'contacto', 'user', 'estado', 'historial', 'ultima_gestion'])
            ->when($request->filled('estado_id'), fn($q) => $q->where('estado_id', $request->integer('estado_id')))
            ->when($request->filled('cliente_id'), fn($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->boolean('exclude_has_quotation'), fn($q) => $q->doesntHave('cotizaciones'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->where(function ($qq) use ($term) {
                    $qq->where('nombre_contacto', 'like', "%{$term}%")
                        ->orWhere('numero_contacto', 'like', "%{$term}%")
                        ->orWhere('empresa', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * POST /api/oportunidades
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_ingreso' => ['nullable', 'date'],
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'numero_contacto' => ['nullable', 'string', 'max:30'],
            'cargo_contacto' => ['nullable', 'string', 'max:150'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'contacto_id' => ['nullable', 'integer', 'exists:contactos,id'],
            'referido_por' => ['nullable', 'string', 'max:150'],

            'email_contacto' => ['nullable', 'string', 'max:150', 'email'],

            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereIn('role', ['ejecutivo', 'superadmin']);
                }),
            ],

            'estado_id' => [
                'required',
                'integer',
                Rule::exists('estados', 'id')->where(function ($q) {
                    $q->where('scope', 'oportunidad');
                }),
            ],
        ]);

        if (empty($data['fecha_ingreso'])) {
            $data['fecha_ingreso'] = now()->toDateString();
        }

        // ✅ Persistir contacto manual si hay cliente pero no contacto seleccionado
        if (!empty($data['cliente_id']) && empty($data['contacto_id'])) {
            // Verificar si hay datos manuales suficientes
            if (!empty($data['nombre_contacto'])) {
                $newContacto = \App\Models\Contacto::create([
                    'cliente_id' => $data['cliente_id'],
                    'nombre' => $data['nombre_contacto'],
                    'cargo' => $data['cargo_contacto'] ?? null,
                    'telefono' => $data['numero_contacto'] ?? null,
                    'email' => $data['email_contacto'] ?? null,
                ]);
                $data['contacto_id'] = $newContacto->id;
            }
        }

        $oportunidad = Oportunidad::create($data);
        $oportunidad->load(['cliente', 'user', 'estado']);

        // Check if created with "Aprobada - Cotización" or "Cotización Enviada"
        $cotizacionId = null;
        if ($oportunidad->estado) {
            $estadoNombre = trim(mb_strtolower($oportunidad->estado->nombre));
            if ($estadoNombre === mb_strtolower('Aprobada - Cotización') || $estadoNombre === mb_strtolower('Cotización Enviada')) {
                $cotizacionId = $this->ensureCotizacionAndCliente($oportunidad);
            }
        }

        // Return additional field
        $response = $oportunidad->toArray();
        if ($cotizacionId) {
            $response['cotizacion_id'] = $cotizacionId;
        }

        return response()->json($response, 201);
    }

    /**
     * GET /api/oportunidades/{oportunidad}
     */
    public function show(Oportunidad $oportunidad)
    {
        $oportunidad->load(['cliente', 'user', 'estado']);
        $oportunidad->load(['cliente', 'contacto', 'user', 'estado', 'historial']);
        return response()->json($oportunidad);
    }

    /**
     * PATCH/PUT /api/oportunidades/{oportunidad}
     */
    public function update(Request $request, Oportunidad $oportunidad)
    {
        $data = $request->validate([
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
            'nombre_contacto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'numero_contacto' => ['sometimes', 'nullable', 'string', 'max:30'],
            'empresa' => ['sometimes', 'nullable', 'string', 'max:150'],
            'referido_por' => ['sometimes', 'nullable', 'string', 'max:150'],
            'cliente_id' => ['sometimes', 'nullable', 'integer', 'exists:clientes,id'],
            'contacto_id' => ['sometimes', 'nullable', 'integer', 'exists:contactos,id'],

            'user_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereIn('role', ['ejecutivo', 'superadmin']);
                }),
            ],

            'estado_id' => [
                'sometimes',
                'integer',
                Rule::exists('estados', 'id')->where(function ($q) {
                    $q->where('scope', 'oportunidad');
                }),
            ],
        ]);

        $oportunidad->fill($data);
        $oportunidad->save();

        // ✅ Log historial de modificación
        \App\Models\HistorialEtapa::create([
            'model_type' => Oportunidad::class,
            'model_id' => $oportunidad->id,
            'user_id' => ($request->user() ? $request->user()->id : 1),
            'estado_anterior_id' => $oportunidad->estado_id,
            'estado_nuevo_id' => $oportunidad->estado_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oportunidad->load(['cliente', 'user', 'estado']);
        return response()->json($oportunidad);
    }

    /**
     * DELETE /api/oportunidades/{oportunidad}
     */
    public function destroy(Oportunidad $oportunidad)
    {
        $oportunidad->delete();
        return response()->noContent();
    }

    /**
     * PATCH /api/oportunidades/{id}/estado
     * Cambiar estado de oportunidad + (gatillo) crear/actualizar cotización
     */
    public function updateEstado(Request $request, Oportunidad $oportunidad)
    {
        $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados,id'],
        ]);

        return DB::transaction(function () use ($request, $oportunidad) {
            $oportunidad = Oportunidad::lockForUpdate()->findOrFail($oportunidad->id);

            $estadoId = (int) $request->input('estado_id');
            $estado = Estado::findOrFail($estadoId);

            // (Opcional) validar scope oportunidad
            // if (($estado->scope ?? null) !== 'oportunidad') { ... }

            $estadoAnteriorId = $oportunidad->estado_id;
            $oportunidad->estado_id = $estadoId;
            $oportunidad->save();

            // Registrar Historial
            if ($estadoAnteriorId !== $estadoId) {
                \App\Models\HistorialEtapa::create([
                    'model_type' => Oportunidad::class,
                    'model_id' => $oportunidad->id,
                    'user_id' => $request->user() ? $request->user()->id : 1,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => $estadoId,
                ]);
            }

            // 🔥 Gatillo: cuando pasa a "Aprobada - Cotización" o "Cotización Enviada"
            $estadoNombre = trim(mb_strtolower($estado->nombre));
            $isAprobadaCot = $estadoNombre === mb_strtolower('Aprobada - Cotización') || $estadoNombre === mb_strtolower('Cotización Enviada');

            if ($isAprobadaCot) {
                $cotizacionId = $this->ensureCotizacionAndCliente($oportunidad);
            }

            return response()->json([
                'ok' => true,
                'oportunidad' => $oportunidad->fresh()->load(['cliente', 'estado', 'historial']),
                'cotizacion_id' => $cotizacionId,
            ]);
        });
    }

    private function ensureCotizacionAndCliente(Oportunidad $oportunidad)
    {
        // 1) Asegurar cliente
        if (!$oportunidad->cliente_id) {
            $cliente = Cliente::create([
                'nombre_empresa' => $oportunidad->empresa,
                'nombre_contacto' => $oportunidad->nombre_contacto,
                'telefono' => $oportunidad->numero_contacto,
                'correo' => null,
                'fecha_ingreso' => now()->toDateString(),
            ]);

            $oportunidad->cliente_id = $cliente->id;
            $oportunidad->save();
        }

        // 2) Crear cotización si no existe (idempotente)
        $existing = Cotizacion::where('oportunidad_id', $oportunidad->id)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        // ✅ Estado por defecto: "Cotización Enviada" (scope=cotizacion)
        $estadoDefaultId = Estado::where('scope', 'cotizacion')
            ->where('nombre', 'Cotización Enviada')
            ->value('id');

        if (!$estadoDefaultId) {
            // Fallback or error? throwing exception to rollback transaction if inside one
            throw new \Exception('No existe el estado "Cotización Enviada" con scope=cotizacion.');
        }

        $user = request()->user();

        $cot = Cotizacion::create([
            'oportunidad_id' => $oportunidad->id,
            'cliente_id' => $oportunidad->cliente_id,
            'user_id' => $user?->id,
            'estado_id' => $estadoDefaultId,
            'fecha_creacion' => now()->toDateString(),
            'fecha_vencimiento' => null,
            'observaciones' => null,
            'origen' => 'oportunidades',
        ]);

        return $cot->id;
    }
}
