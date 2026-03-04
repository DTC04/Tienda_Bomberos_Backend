<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventoController extends Controller
{
    /**
     * GET /api/eventos
     * Lista los eventos para el calendario.
     * Recibe parámetros 'start' y 'end' (fechas ISO) enviados por FullCalendar.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([], 401);
        }

        $isSuperAdmin = $user->role === 'superadmin';
        $ejecutivoId = $request->query('ejecutivo_id') ?? $user->id;
        $start = $request->query('start');
        $end = $request->query('end');

        if (!$start || !$end) {
            return response()->json([], 200);
        }

        // 1. Consultar eventos en BD
        $query = Evento::query()
            ->where(function ($q) use ($start, $end) {
                $q->where('inicio', '<=', Carbon::parse($end)->endOfDay())
                    ->where(function ($sub) use ($start) {
                        $sub->where('fin', '>=', Carbon::parse($start)->startOfDay())
                            ->orWhereNull('fin');
                    });
            })
            ->with(['oportunidad:id,empresa,nombre_contacto', 'ejecutivo:id,name']);

        // Si es superadmin, ve TODOS los eventos. Si no, solo los propios + corporativos + área
        if (!$isSuperAdmin) {
            $query->where(function ($q) use ($ejecutivoId, $user) {
                $q->where('ejecutivo_id', $ejecutivoId)
                    ->orWhere('nivel', 'corporativo')
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('nivel', 'area')
                            ->where('area', $user->role);
                    });
            });
        }

        $eventos = $query->get();

        // 2. Inyección Automática: Vencimientos de Cotizaciones
        $cotQuery = \App\Models\Cotizacion::query()
            ->whereBetween('fecha_vencimiento', [$start, $end])
            ->with(['oportunidad:id,empresa,nombre_contacto', 'cliente:id,nombre_empresa,nombre_contacto', 'ejecutivo:id,name', 'estado:id,nombre']);

        if (!$isSuperAdmin) {
            $cotQuery->where('user_id', $ejecutivoId);
        }

        $cotizaciones = $cotQuery->get();

        // 3. Formatear Eventos de BD
        $data = $eventos->map(function ($ev) use ($isSuperAdmin) {
            $color = match ($ev->tipo) {
                'reunion' => '#3b82f6',
                'seguimiento' => '#eab308',
                'cierre' => '#10b981',
                'vencimiento' => '#ef4444',
                'llamada' => '#06b6d4',
                'otro' => '#64748b',
                default => match ($ev->nivel) {
                        'corporativo' => '#6366f1',
                        'area' => '#8b5cf6',
                        default => '#6b7280'
                    }
            };

            $titulo = $ev->titulo;
            if ($ev->nivel === 'corporativo')
                $titulo = "📢 " . $titulo;
            if ($ev->nivel === 'area')
                $titulo = "🏢 " . $titulo;

            // Para SuperAdmin: mostrar nombre del usuario dueño
            if ($isSuperAdmin && $ev->ejecutivo) {
                $titulo .= " [" . $ev->ejecutivo->name . "]";
            }

            if ($ev->oportunidad) {
                $titulo .= " ({$ev->oportunidad->empresa})";
            }

            return [
                'id' => 'manual-' . $ev->id,
                'title' => $titulo,
                'start' => Carbon::parse($ev->inicio)->toIso8601String(),
                'end' => $ev->fin ? Carbon::parse($ev->fin)->toIso8601String() : null,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'descripcion' => $ev->descripcion,
                    'tipo' => $ev->tipo,
                    'nivel' => $ev->nivel ?? 'usuario',
                    'categoria' => $ev->categoria,
                    'oportunidad_id' => $ev->oportunidad_id,
                    'nombre_contacto' => $ev->oportunidad?->nombre_contacto,
                    'ejecutivo_nombre' => $ev->ejecutivo?->name,
                ]
            ];
        });

        // 4. Formatear Cotizaciones como Eventos Virtuales
        $cotData = $cotizaciones->map(function ($cot) use ($isSuperAdmin) {
            $color = '#ef4444';
            $numDisplay = $cot->numero ?? $cot->id;
            $titulo = "⏳ #{$numDisplay} Vence CT";

            if ($cot->oportunidad)
                $titulo .= " ({$cot->oportunidad->empresa})";
            else if ($cot->cliente)
                $titulo .= " (" . ($cot->cliente->nombre_empresa ?? $cot->cliente->nombre_contacto) . ")";

            if ($isSuperAdmin && $cot->ejecutivo) {
                $titulo .= " [" . $cot->ejecutivo->name . "]";
            }

            $vDate = Carbon::parse($cot->fecha_vencimiento)->toDateString();
            $clienteNombre = $cot->cliente
                ? ($cot->cliente->nombre_empresa ?? $cot->cliente->nombre_contacto ?? '-')
                : '-';

            $contacto = $cot->cliente->nombre_contacto ?? $cot->oportunidad->nombre_contacto ?? '-';
            $estadoNombre = $cot->estado->nombre ?? 'N/A';

            $descripcion = "Cotización: {$cot->nombre}\n"
                . "Número: #" . ($cot->numero ?? $cot->id) . "\n"
                . "Cliente: {$clienteNombre}\n"
                . "Contacto: {$contacto}\n"
                . "Estado: {$estadoNombre}\n"
                . "Monto Total: $" . number_format($cot->total ?? 0, 0, ',', '.') . "\n"
                . "Fecha límite de validez de la cotización.";

            return [
                'id' => 'quote-' . $cot->id,
                'title' => $titulo,
                'start' => Carbon::parse($vDate . ' 09:00:00')->toIso8601String(),
                'end' => Carbon::parse($vDate . ' 10:00:00')->toIso8601String(),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'allDay' => true,
                'extendedProps' => [
                    'tipo' => 'vencimiento_automatico',
                    'cotizacion_id' => $cot->id,
                    'cotizacion_nombre' => $cot->nombre,
                    'cotizacion_numero' => $cot->numero,
                    'cliente_nombre' => $clienteNombre,
                    'contacto_nombre' => $contacto,
                    'estado_nombre' => $estadoNombre,
                    'monto_total' => $cot->total,
                    'descripcion' => $descripcion,
                    'ejecutivo_nombre' => $cot->ejecutivo?->name,
                ]
            ];
        });

        return response()->json($data->concat($cotData));
    }

    /**
     * POST /api/eventos
     * Crear un nuevo evento manualmente.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'inicio' => 'required|date',
            'fin' => 'nullable|date|after_or_equal:inicio',
            'tipo' => 'nullable|string',
            'oportunidad_id' => 'nullable|exists:oportunidades,id',
            'nivel' => 'nullable|string|in:usuario,area,corporativo',
            'area' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Seguridad: Solo SuperAdmin puede crear eventos corporativos
        $nivelFinal = $data['nivel'] ?? 'usuario';
        if ($nivelFinal === 'corporativo' && $user->role !== 'superadmin') {
            $nivelFinal = 'area'; // Si no es admin, lo bajamos a nivel área por seguridad
        }

        $evento = Evento::create([
            'ejecutivo_id' => $user->id,
            'oportunidad_id' => $data['oportunidad_id'] ?? null,
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'] ?? null,
            'inicio' => $data['inicio'],
            'fin' => $data['fin'] ?? null,
            'tipo' => $data['tipo'] ?? 'reunion',
            'nivel' => $nivelFinal,
            'area' => $data['area'] ?? $user->role,
        ]);

        return response()->json($evento->load('oportunidad'), 201);
    }

    /**
     * PUT /api/eventos/{id}
     * Actualizar evento (ej: Drag & Drop en el calendario).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        // SuperAdmin puede editar cualquiera; otros solo los suyos
        $query = Evento::where('id', $id);
        if ($user->role !== 'superadmin') {
            $query->where('ejecutivo_id', $user->id);
        }
        $evento = $query->firstOrFail();

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:150',
            'inicio' => 'sometimes|date',
            'fin' => 'nullable|date|after_or_equal:inicio',
            'tipo' => 'sometimes|string|in:reunion,vencimiento,seguimiento,cierre',
            'descripcion' => 'nullable|string',
        ]);

        $evento->update($data);

        return response()->json($evento);
    }

    /**
     * DELETE /api/eventos/{id}
     * Eliminar evento.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        // SuperAdmin puede eliminar cualquiera; otros solo los suyos
        $query = Evento::where('id', $id);
        if ($user->role !== 'superadmin') {
            $query->where('ejecutivo_id', $user->id);
        }
        $evento = $query->firstOrFail();

        $evento->delete();

        return response()->noContent();
    }
}