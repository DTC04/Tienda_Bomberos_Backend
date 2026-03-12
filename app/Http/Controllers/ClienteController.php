<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * GET /api/clientes
     * Listar clientes (paginado + búsqueda)
     */
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Log::info("API Req", $request->all());
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $query = Cliente::query()
            ->with(['region', 'provincia', 'children', 'parent']) // Eager load children count or children?
            ->when($request->filled('zona_id'), function ($q) use ($request) {
                $q->whereHas('region', function ($sq) use ($request) {
                    $sq->where('zona_id', $request->query('zona_id'));
                });
            })
            ->when($request->filled('region_id'), function ($q) use ($request) {
                $q->where('region_id', $request->query('region_id'));
            })
            ->when($request->filled('provincia_id'), function ($q) use ($request) {
                $q->where('provincia_id', $request->query('provincia_id'));
            });

        // Hierarchy filters
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        } elseif ($request->filled('parent_id')) {
            $query->where('parent_id', $request->query('parent_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre_empresa', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 500)); // Allow up to 500 for large lists

        $clientes = $query->orderBy('nombre_empresa')->paginate($perPage);

        \Illuminate\Support\Facades\Log::info("API SQL", \Illuminate\Support\Facades\DB::getQueryLog());

        return response()->json($clientes);
    }

    /**
     * POST /api/clientes
     * Crear cliente
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:15'], // Optional manual ID payload
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'giro' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'correo' => ['nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20'],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'cuerpo_id' => ['nullable', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'exists:companias,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['fecha_ingreso'])) {
            $data['fecha_ingreso'] = now()->toDateString();
        }

        // Si no se envía un ID (por ejemplo, clientes normales), generamos uno secuencial manteniéndolo en el formato string.
        if (empty($data['id'])) {
            // Buscamos el ID numérico máximo que sea menor a 1,000,000 (para no chocar con los de bomberos que son 7+ dígitos)
            $maxId = \Illuminate\Support\Facades\DB::table('clientes')
                ->whereRaw('LENGTH(id) < 7')
                ->max(\Illuminate\Support\Facades\DB::raw('CAST(id AS UNSIGNED)'));

            $nextId = $maxId ? $maxId + 1 : 1;
            $data['id'] = (string) $nextId;
        }

        $cliente = Cliente::create($data);

        return response()->json($cliente, 201);
    }

    /**
     * GET /api/clientes/{cliente}
     */
    public function show(Cliente $cliente)
    {
        return response()->json($cliente->load(['region', 'provincia', 'parent']));
    }

    /**
     * PATCH/PUT /api/clientes/{cliente}
     */
    public function update(Request $request, Cliente $cliente)
    {
        $data = $request->validate([
            'nombre_empresa' => ['sometimes', 'required', 'string', 'max:150'],
            'giro' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:100'],
            'correo' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20'],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'cuerpo_id' => ['nullable', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'exists:companias,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $cliente->update($data);

        return response()->json($cliente);
    }

    /**
     * DELETE /api/clientes/{cliente}
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return response()->noContent();
    }

    /**
     * POST /api/clientes/{cliente}/logo
     * Subir el logo institucional
     */
    public function uploadLogo(Request $request, Cliente $cliente)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB Max
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('clientes/logos_' . $cliente->id, 'public');

            $url = \Illuminate\Support\Facades\Storage::url($path);

            // Actualizar la URL en la BD
            $cliente->update(['logo_url' => $url]);

            return response()->json([
                'url' => $url,
                'message' => 'Logo actualizado correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir el logo'], 500);
        }
    }
}
