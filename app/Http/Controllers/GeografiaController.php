<?php

namespace App\Http\Controllers;

use App\Models\Provincia;
use App\Models\Region;
use App\Models\Zona;
use Illuminate\Http\Request;

class GeografiaController extends Controller
{
    public function zonas()
    {
        return response()->json(Zona::all());
    }

    public function regiones(Request $request)
    {
        $query = Region::query();

        if ($request->filled('zona_id')) {
            $query->where('zona_id', $request->query('zona_id'));
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    public function provincias(Request $request)
    {
        $query = Provincia::query();

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }

        return response()->json($query->orderBy('nombre')->get());
    }
}
