<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\Cuerpo;
use App\Models\Compania;
use App\Models\Cliente;
use Carbon\Carbon;

class ImportBomberosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = '/Users/diegoignacio/Desktop/Tienda Bomberos/bomberos_mapped.json';

        if (!File::exists($jsonPath)) {
            $this->command->error("Archivo JSON no encontrado en: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        $this->command->info('Importando ' . count($data) . ' Cuerpos de Bomberos y Compañias...');

        DB::beginTransaction();
        try {
            foreach ($data as $row) {
                $logicRegionId = (int) $row['Region'];
                $numeroSocio = (int) (float) $row['Numero de Socio'];
                $cuerpoNombre = $row['Cuerpo de Bomberos'];
                $rut = $row['RUT'] === 'None' || $row['RUT'] === 'NaT' ? null : $row['RUT'];

                // Map logic region (1-16) to DB region_id
                $logicToDbMap = [
                    1 => 1,
                    2 => 2,
                    3 => 3,
                    4 => 4,
                    5 => 6, // Valparaíso logic: 5, db: 6
                    6 => 7, // O'Higgins logic: 6, db: 7
                    7 => 8, // Maule logic: 7, db: 8
                    8 => 9, // Biobio logic: 8, db: 9
                    9 => 5, // Araucanía logic: 9, db: 5
                    10 => 10,
                    11 => 11,
                    12 => 12,
                    13 => 13,
                    14 => 14,
                    15 => 15,
                    16 => 16
                ];
                $dbRegionId = $logicToDbMap[$logicRegionId] ?? $logicRegionId;

                // Parse date
                $fechaFundacion = null;
                if (!empty($row['Fecha de Fundacion']) && $row['Fecha de Fundacion'] !== 'NaT' && $row['Fecha de Fundacion'] !== 'None') {
                    try {
                        $fechaFundacion = Carbon::createFromFormat('d-m-Y', trim($row['Fecha de Fundacion']))->format('Y-m-d');
                    } catch (\Exception $e) {
                        try {
                            $fechaFundacion = Carbon::parse(trim($row['Fecha de Fundacion']))->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $fechaFundacion = null;
                        }
                    }
                }

                // Format ID: Region (2) + Socio (3) + 00 (2)
                $cuerpoId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                    str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                    '00';

                // 1. Create or Update Cuerpo
                Cuerpo::updateOrCreate(
                    ['id' => $cuerpoId],
                    [
                        'region_id' => $dbRegionId,
                        'nombre' => $cuerpoNombre,
                        'numero_socio' => $numeroSocio,
                        'rut' => $rut,
                        'fecha_fundacion' => $fechaFundacion,
                        'direccion' => $row['Direccion'] === 'None' ? null : $row['Direccion'],
                        'telefono' => $row['Telefono'] === 'None' ? null : $row['Telefono'],
                        'numero_companias' => $row['Numero de Companias'] ?? 0,
                        'logo_url' => $row['Logo Path'] === 'None' ? null : $row['Logo Path'],
                    ]
                );

                // Create Contacts
                $superName = $row['Superintendente'] !== 'None' ? trim($row['Superintendente']) : null;
                if ($superName) {
                    \App\Models\Contacto::updateOrCreate(
                        ['cliente_id' => $cuerpoId, 'cargo' => 'Superintendente'],
                        ['nombre' => $superName]
                    );
                }

                $comandName = $row['Comandante'] !== 'None' ? trim($row['Comandante']) : null;
                if ($comandName) {
                    \App\Models\Contacto::updateOrCreate(
                        ['cliente_id' => $cuerpoId, 'cargo' => 'Comandante'],
                        ['nombre' => $comandName]
                    );
                }

                // 2. Create or Update Cliente corresponding to Cuerpo
                Cliente::updateOrCreate(
                    ['id' => $cuerpoId],
                    [
                        'nombre_empresa' => 'Cuerpo de Bomberos ' . $cuerpoNombre,
                        'rut_empresa' => $rut,
                        'logo_url' => $row['Logo Path'] === 'None' ? null : $row['Logo Path'],
                        'direccion' => $row['Direccion'] === 'None' ? null : $row['Direccion'],
                        'telefono' => $row['Telefono'] === 'None' ? null : $row['Telefono'],
                        'numero_companias' => $row['Numero de Companias'] ?? 0,
                        'region_id' => $dbRegionId,
                        'cuerpo_id' => $cuerpoId,
                    ]
                );

                // 3. Create or Update Companias
                $numCompanias = (int) ($row['Numero de Companias'] ?? 0);
                if ($numCompanias > 0) {
                    for ($i = 1; $i <= $numCompanias; $i++) {
                        $companiaNumStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $companiaId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                            str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                            $companiaNumStr;

                        Compania::updateOrCreate(
                            ['id' => $companiaId],
                            [
                                'cuerpo_id' => $cuerpoId,
                                'nombre' => $i . 'ª Compañía ' . $cuerpoNombre,
                                'numero' => $i,
                            ]
                        );

                        Cliente::updateOrCreate(
                            ['id' => $companiaId],
                            [
                                'nombre_empresa' => $i . 'ª Compañía ' . $cuerpoNombre,
                                'rut_empresa' => $rut,
                                'direccion' => $row['Direccion'] === 'None' ? null : $row['Direccion'],
                                'telefono' => $row['Telefono'] === 'None' ? null : $row['Telefono'],
                                'region_id' => $dbRegionId,
                                'cuerpo_id' => $cuerpoId,
                                'compania_id' => $companiaId,
                            ]
                        );
                    }

                    // Special Brigade (99)
                    $brigadaId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                        str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                        '99';

                    Compania::updateOrCreate(
                        ['id' => $brigadaId],
                        [
                            'cuerpo_id' => $cuerpoId,
                            'nombre' => 'Brigada Especializada ' . $cuerpoNombre,
                            'numero' => 99,
                        ]
                    );

                    Cliente::updateOrCreate(
                        ['id' => $brigadaId],
                        [
                            'nombre_empresa' => 'Brigada Especializada ' . $cuerpoNombre,
                            'rut_empresa' => $rut ?? null,
                            'region_id' => $dbRegionId,
                            'cuerpo_id' => $cuerpoId,
                            'compania_id' => $brigadaId,
                        ]
                    );
                }
            }
            DB::commit();
            $this->command->info('Proceso completado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error durante la importación: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
