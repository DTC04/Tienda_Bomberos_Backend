<?php

namespace App\Http\Controllers;

use App\Models\Cuerpo;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuerpoController extends Controller
{
    public function index(Request $request)
    {
        $query = Cuerpo::with(['region']);

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('nombre', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'region_id' => 'required|exists:regiones,id',
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'fecha_fundacion' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'numero_companias' => 'nullable|integer',
            'numero_socio' => 'nullable|integer'
        ]);

        return DB::transaction(function () use ($data) {
            $regionId = $data['region_id'];

            // Map DB region_id to logical region prefix for 7-digit ID
            $dbToLogicMap = [
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                6 => 5, // Valparaíso DB:6 -> Logic:5
                7 => 6, // O'Higgins DB:7 -> Logic:6
                8 => 7, // Maule DB:8 -> Logic:7
                9 => 8, // Biobio DB:9 -> Logic:8
                5 => 9, // Araucanía DB:5 -> Logic:9
                10 => 10,
                11 => 11,
                12 => 12,
                13 => 13,
                14 => 14,
                15 => 15,
                16 => 16
            ];
            $logicRegionPrefix = $dbToLogicMap[$regionId] ?? $regionId;

            if (empty($data['numero_socio'])) {
                $maxSocio = Cuerpo::where('region_id', $regionId)->max('numero_socio') ?? 0;
                $data['numero_socio'] = $maxSocio + 1;
            }

            $numeroSocio = $data['numero_socio'];

            $cuerpoId = str_pad($logicRegionPrefix, 2, '0', STR_PAD_LEFT) .
                str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                '00';

            $data['id'] = $cuerpoId;
            $cuerpo = Cuerpo::create($data);

            Cliente::create([
                'id' => $cuerpoId,
                'nombre_empresa' => 'Cuerpo de Bomberos ' . $data['nombre'],
                'rut_empresa' => $data['rut'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'numero_companias' => $data['numero_companias'] ?? 0,
                'region_id' => $regionId,
                'cuerpo_id' => $cuerpoId,
                'fecha_fundacion' => $data['fecha_fundacion'] ?? null,
            ]);

            return response()->json($cuerpo->load('region'), 201);
        });
    }

    public function show($id)
    {
        return response()->json(Cuerpo::with('region', 'companias')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $cuerpo = Cuerpo::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'fecha_fundacion' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'numero_companias' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($cuerpo, $data) {
            $cuerpo->update($data);

            $cliente = Cliente::find($cuerpo->id);
            if ($cliente) {
                $clienteUpdate = [];
                if (isset($data['nombre']))
                    $clienteUpdate['nombre_empresa'] = 'Cuerpo de Bomberos ' . $data['nombre'];
                if (array_key_exists('rut', $data))
                    $clienteUpdate['rut_empresa'] = $data['rut'];
                if (array_key_exists('direccion', $data))
                    $clienteUpdate['direccion'] = $data['direccion'];
                if (array_key_exists('telefono', $data))
                    $clienteUpdate['telefono'] = $data['telefono'];
                if (array_key_exists('numero_companias', $data))
                    $clienteUpdate['numero_companias'] = $data['numero_companias'];
                if (array_key_exists('fecha_fundacion', $data))
                    $clienteUpdate['fecha_fundacion'] = $data['fecha_fundacion'];
                $cliente->update($clienteUpdate);
            }

            return response()->json($cuerpo);
        });
    }

    public function destroy($id)
    {
        $cuerpo = Cuerpo::findOrFail($id);
        $cuerpo->delete();
        Cliente::where('id', $id)->delete();

        return response()->noContent();
    }
}
