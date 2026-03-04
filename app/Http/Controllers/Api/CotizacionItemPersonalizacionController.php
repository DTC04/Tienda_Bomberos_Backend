<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DetalleCotizacion;
use Illuminate\Support\Facades\DB;

class CotizacionItemPersonalizacionController extends Controller
{
    public function show($cotizacion_item_id)
    {
        $item = DetalleCotizacion::with([
            'gruposPersonalizacion.tallas',
            'gruposPersonalizacion.matrices',
            'gruposPersonalizacion.variables'
        ])->findOrFail($cotizacion_item_id);

        return response()->json([
            'grupos' => $item->gruposPersonalizacion
        ]);
    }

    public function update(Request $request, $cotizacion_item_id)
    {
        $item = DetalleCotizacion::findOrFail($cotizacion_item_id);

        $request->validate([
            'grupos' => 'required|array',
            'grupos.*.nombre_grupo' => 'nullable|string',
            'grupos.*.tallas' => 'array',
            'grupos.*.matrices' => 'array',
            'grupos.*.variables' => 'array',
        ]);

        DB::beginTransaction();

        try {
            // Eliminar grupos existentes (por cascade, elimina tallas, matrices y variables)
            $item->gruposPersonalizacion()->delete();

            // Recrear la estructura
            foreach ($request->grupos as $grupoData) {
                $grupo = $item->gruposPersonalizacion()->create([
                    'nombre_grupo' => $grupoData['nombre_grupo'] ?? 'General',
                    'archivo_adjunto' => $grupoData['archivo_adjunto'] ?? null,
                ]);

                if (!empty($grupoData['tallas'])) {
                    $grupo->tallas()->createMany($grupoData['tallas']);
                }

                if (!empty($grupoData['matrices'])) {
                    $grupo->matrices()->createMany($grupoData['matrices']);
                }

                if (!empty($grupoData['variables'])) {
                    $grupo->variables()->createMany($grupoData['variables']);
                }
            }

            DB::commit();

            // Retornar la estructura actualizada
            $item->load([
                'gruposPersonalizacion.tallas',
                'gruposPersonalizacion.matrices',
                'gruposPersonalizacion.variables'
            ]);

            return response()->json([
                'message' => 'Personalización guardada correctamente',
                'grupos' => $item->gruposPersonalizacion
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al guardar personalización: ' . $e->getMessage()], 500);
        }
    }

    public function uploadMatrizImage(Request $request, $cotizacion_item_id)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB Max
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('personalizaciones/item_' . $cotizacion_item_id, 'public');

            return response()->json([
                'url' => \Illuminate\Support\Facades\Storage::url($path),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir la imagen'], 500);
        }
    }

    public function uploadNames(Request $request, $cotizacion_item_id)
    {
        $request->validate([
            // PDF, Excel, Word, CSV... limit to 10MB
            'file' => ['required', 'file', 'mimes:pdf,xls,xlsx,csv,doc,docx,txt', 'max:10240'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('personalizaciones/nombres_' . $cotizacion_item_id, 'public');

            $nombres_extraidos = [];
            $extension = strtolower($file->getClientOriginalExtension());

            if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    foreach ($rows as $row) {
                        // asumiendo que la primera columna tiene el nombre
                        if (!empty($row[0])) {
                            $stringVal = trim((string) $row[0]);
                            if ($stringVal !== '' && strtolower($stringVal) !== 'nombre' && strtolower($stringVal) !== 'nombres') {
                                $nombres_extraidos[] = $stringVal;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Error parseando excel: " . $e->getMessage());
                }
            } else if ($extension === 'txt') {
                $content = file_get_contents($file->getRealPath());
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $val = trim($line);
                    if ($val !== '') {
                        $nombres_extraidos[] = $val;
                    }
                }
            }

            return response()->json([
                'url' => \Illuminate\Support\Facades\Storage::url($path),
                'name' => $file->getClientOriginalName(),
                'nombres_extraidos' => $nombres_extraidos
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir el archivo de nombres'], 500);
        }
    }
}
