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
                    ->orWhere('nombre_contacto', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%");
            });
            // If searching, we might want to ignore hierarchy or show matches?
            // For now, let's respect the hierarchy filters if present, or search globally if not.
            // If root_only is NOT set and parent_id is NOT set, but search IS set, maybe we search globally?
            // But if the UI defaults to root_only=true, then search will only search roots.
            // Adjust UI logic later.
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 500)); // Allow up to 500 for large lists

        $clientes = $query->orderBy('nombre_empresa')->paginate($perPage);

        return response()->json($clientes);
    }

    /**
     * POST /api/clientes
     * Crear cliente
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'giro' => ['nullable', 'string', 'max:255'],
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:100'], // Increased max length
            'correo' => ['nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20'],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'superintendente' => ['nullable', 'string', 'max:255'],
            'comandante' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['fecha_ingreso'])) {
            $data['fecha_ingreso'] = now()->toDateString();
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
            'nombre_contacto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:100'],
            'correo' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20'],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'superintendente' => ['nullable', 'string', 'max:255'],
            'comandante' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $cliente->fill($data)->save();

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
