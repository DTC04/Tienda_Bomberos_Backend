<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\F_MpProveedor;
use App\Models\F_MpMateriaPrima;
use App\Models\F_MpEspecificacion;
use App\Models\F_MpStock;
use App\Models\F_OrdenProduccion;
use App\Models\F_ControlCalidad;
use App\Models\F_FichaTecnica;

class FabricaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear proveedores de materias primas
        $proveedores = [
            [
                'nombre_fantasia' => 'Textiles del Norte S.A.',
                'razon_social' => 'Textiles del Norte S.A.',
                'rut_empresa' => '76.123.456-7',
                'telefono' => '+56 2 2234 5678',
                'email' => 'ventas@textilesnorte.cl'
            ],
            [
                'nombre_fantasia' => 'Fibras y Telas Ltda.',
                'razon_social' => 'Fibras y Telas Ltda.',
                'rut_empresa' => '96.789.123-4',
                'telefono' => '+56 9 8765 4321',
                'email' => 'contacto@fibrasytel.cl'
            ],
            [
                'nombre_fantasia' => 'Materiales Industriales S.A.',
                'razon_social' => 'Materiales Industriales S.A.',
                'rut_empresa' => '77.456.789-1',
                'telefono' => '+56 2 2987 6543',
                'email' => 'info@materiales.cl'
            ],
        ];

        foreach ($proveedores as $proveedor) {
            F_MpProveedor::create($proveedor);
        }

        // Crear materias primas
        $materiasPrimas = [
            [
                'tipo_material' => 'Tela',
                'nombre' => 'Tela Nomex IIIA',
                'unidad_medida' => 'metros',
                'requiere_especificacion' => true,
                'ancho_estandar' => 150.00
            ],
            [
                'tipo_material' => 'Tela',
                'nombre' => 'Tela Kevlar',
                'unidad_medida' => 'metros',
                'requiere_especificacion' => true,
                'ancho_estandar' => 140.00
            ],
            [
                'tipo_material' => 'Cinta',
                'nombre' => 'Cinta Reflectiva 3M',
                'unidad_medida' => 'metros',
                'requiere_especificacion' => false,
                'ancho_estandar' => 5.00
            ],
            [
                'tipo_material' => 'Hilo',
                'nombre' => 'Hilo Nomex',
                'unidad_medida' => 'conos',
                'requiere_especificacion' => false,
                'ancho_estandar' => null
            ],
            [
                'tipo_material' => 'Accesorio',
                'nombre' => 'Cremallera Ignífuga',
                'unidad_medida' => 'unidades',
                'requiere_especificacion' => false,
                'ancho_estandar' => null
            ],
        ];

        foreach ($materiasPrimas as $materiaPrima) {
            F_MpMateriaPrima::create($materiaPrima);
        }

        // Crear especificaciones para materias primas
        $especificaciones = [
            [
                'materia_prima_id' => 1, // Nomex IIIA
                'proveedor_id' => 1,
                'fecha_ingreso' => '2024-12-01',
                'lote_proveedor' => 'NMX-2024-001',
                'ancho_real' => 148.50,
                'gramaje' => 220.00
            ],
            [
                'materia_prima_id' => 1, // Nomex IIIA
                'proveedor_id' => 2,
                'fecha_ingreso' => '2024-12-15',
                'lote_proveedor' => 'FT-NMX-456',
                'ancho_real' => 149.80,
                'gramaje' => 225.00
            ],
            [
                'materia_prima_id' => 2, // Kevlar
                'proveedor_id' => 1,
                'fecha_ingreso' => '2024-11-20',
                'lote_proveedor' => 'KEV-2024-789',
                'ancho_real' => 139.20,
                'gramaje' => 300.00
            ],
        ];

        foreach ($especificaciones as $especificacion) {
            F_MpEspecificacion::create($especificacion);
        }

        // Crear stocks
        $stocks = [
            [
                'especificacion_id' => 1,
                'cantidad_actual' => 250.50,
                'estado' => 'Disponible'
            ],
            [
                'especificacion_id' => 2,
                'cantidad_actual' => 180.75,
                'estado' => 'Disponible'
            ],
            [
                'especificacion_id' => 3,
                'cantidad_actual' => 75.20,
                'estado' => 'Stock Bajo'
            ],
        ];

        foreach ($stocks as $stock) {
            F_MpStock::create($stock);
        }

        // Crear fichas técnicas (necesitamos que existan SKUs primero)
        // Verificar si existen SKUs para crear fichas técnicas
        $skus = \DB::table('pte_skus')->limit(3)->get();
        
        if ($skus->isNotEmpty()) {
            $fichasTecnicas = [
                [
                    'sku' => $skus[0]->sku ?? 'SKU001',
                    'materia_prima_id' => 1,
                    'cantidad_requerida' => 2.5
                ],
                [
                    'sku' => $skus[0]->sku ?? 'SKU001',
                    'materia_prima_id' => 3,
                    'cantidad_requerida' => 0.8
                ],
            ];

            foreach ($fichasTecnicas as $ficha) {
                try {
                    F_FichaTecnica::create($ficha);
                } catch (\Exception $e) {
                    // Si hay error (SKU no existe), continuar
                    continue;
                }
            }
        }

        // Crear órdenes de producción (necesitamos que existan peticiones)
        $peticiones = \DB::table('peticiones')->limit(2)->get();
        
        if ($peticiones->isNotEmpty() && $skus->isNotEmpty()) {
            $ordenes = [
                [
                    'peticion_id' => $peticiones[0]->id,
                    'sku' => $skus[0]->sku ?? 'SKU001',
                    'cantidad_a_producir' => 50,
                    'estado_produccion' => 'Programada'
                ],
                [
                    'peticion_id' => $peticiones[1]->id ?? $peticiones[0]->id,
                    'sku' => $skus[1]->sku ?? $skus[0]->sku ?? 'SKU002',
                    'cantidad_a_producir' => 25,
                    'estado_produccion' => 'En Proceso'
                ],
            ];

            foreach ($ordenes as $orden) {
                try {
                    F_OrdenProduccion::create($orden);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Crear controles de calidad
            $controles = [
                [
                    'orden_produccion_id' => 1,
                    'fecha_inspeccion' => '2024-12-20',
                    'cantidad_aprobada' => 45,
                    'cantidad_rechazada' => 5,
                    'inspector_id' => 1,
                    'observaciones' => 'Leve defecto en acabado de 5 unidades'
                ],
            ];

            foreach ($controles as $control) {
                try {
                    F_ControlCalidad::create($control);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $this->command->info('✅ Datos de fábrica creados exitosamente');
        $this->command->info('📦 Proveedores: ' . F_MpProveedor::count());
        $this->command->info('🧵 Materias Primas: ' . F_MpMateriaPrima::count());
        $this->command->info('📋 Especificaciones: ' . F_MpEspecificacion::count());
        $this->command->info('📊 Stocks: ' . F_MpStock::count());
        $this->command->info('🏭 Órdenes de Producción: ' . F_OrdenProduccion::count());
        $this->command->info('✅ Controles de Calidad: ' . F_ControlCalidad::count());
    }
}