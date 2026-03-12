<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use App\Models\Cuerpo;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompaniaController extends Controller
{
    public function index(Request $request)
    {
        $query = Compania::with(['cuerpo']);

        if ($request->filled('cuerpo_id')) {
            $query->where('cuerpo_id', $request->query('cuerpo_id'));
        }

        return response()->json($query->orderBy('numero')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cuerpo_id' => 'required|exists:cuerpos,id',
            'nombre' => 'required|string|max:255',
            'numero' => 'required|integer',
            'fecha_fundacion' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($data) {
            $cuerpo = Cuerpo::findOrFail($data['cuerpo_id']);

            $cuerpoPrefix = substr($cuerpo->id, 0, 5);
            $companiaNumStr = str_pad($data['numero'], 2, '0', STR_PAD_LEFT);
            $companiaId = $cuerpoPrefix . $companiaNumStr;

            if (Compania::find($companiaId)) {
                return response()->json(['error' => 'Ya existe una compañía con este número en este cuerpo.'], 422);
            }

            $data['id'] = $companiaId;
            $compania = Compania::create($data);

            Cliente::create([
                'id' => $companiaId,
                'nombre_empresa' => $data['nombre'],
                'region_id' => $cuerpo->region_id,
                'cuerpo_id' => $cuerpo->id,
                'compania_id' => $companiaId,
                'parent_id' => $cuerpo->id, // Compatibilidad frontend legacy
                'rut_empresa' => $cuerpo->rut,
                'direccion' => $cuerpo->direccion,
                'telefono' => $cuerpo->telefono,
                'fecha_fundacion' => $data['fecha_fundacion'] ?? null,
            ]);

            return response()->json($compania->load('cuerpo'), 201);
        });
    }

    public function show($id)
    {
        return response()->json(Compania::with('cuerpo')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $compania = Compania::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'numero' => 'sometimes|required|integer',
            'fecha_fundacion' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($compania, $data) {
            $updateData = [];
            if (isset($data['nombre']))
                $updateData['nombre'] = $data['nombre'];
            if (isset($data['numero']))
                $updateData['numero'] = $data['numero'];
            if (!empty($updateData)) {
                $compania->update($updateData);
            }

            $clienteUpdate = [];
            if (isset($data['nombre']))
                $clienteUpdate['nombre_empresa'] = $data['nombre'];
            if (array_key_exists('fecha_fundacion', $data))
                $clienteUpdate['fecha_fundacion'] = $data['fecha_fundacion'];

            if (!empty($clienteUpdate)) {
                Cliente::where('id', $compania->id)->update($clienteUpdate);
            }

            return response()->json($compania);
        });
    }

    public function destroy($id)
    {
        $compania = Compania::findOrFail($id);
        $compania->delete();
        Cliente::where('id', $id)->delete();

        return response()->noContent();
    }
}
