<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contacto;
use Illuminate\Http\Request;

class ContactoController extends Controller
{
    public function index(Cliente $cliente)
    {
        return response()->json($cliente->contactos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
        ]);

        $contacto = Contacto::create($validated);
        return response()->json($contacto, 201);
    }

    public function update(Request $request, Contacto $contacto)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'rut' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
        ]);

        $contacto->update($validated);
        return response()->json($contacto);
    }

    public function destroy(Contacto $contacto)
    {
        $contacto->delete();
        return response()->json(null, 204);
    }
}