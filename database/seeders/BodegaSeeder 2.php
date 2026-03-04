<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MpTipo;
use App\Models\MpUnidad;
use App\Models\MpAncho;
use App\Models\MpColor;
use App\Models\MpProveedor;
use App\Models\MpMaterial;
use App\Models\MpLote;
use Carbon\Carbon;

class BodegaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CATÁLOGOS BÁSICOS
        $tipos = [
            'Tela', 'Insumo', 'Hilo', 'Etiqueta', 'Cierre'
        ];
        foreach ($tipos as $nombre) {
            MpTipo::firstOrCreate(['nombre' => $nombre]);
        }

        $unidades = [
            ['nombre' => 'Metros', 'abreviacion' => 'Mts'],
            ['nombre' => 'Kilos',  'abreviacion' => 'Kg'],
            ['nombre' => 'Unidades', 'abreviacion' => 'Ud'],
            ['nombre' => 'Litros', 'abreviacion' => 'Lts'],
        ];
        foreach ($unidades as $u) {
            MpUnidad::firstOrCreate(['nombre' => $u['nombre']], $u);
        }

        $anchos = ['1.50m', '1.60m', '1.80m', 'N/A'];
        foreach ($anchos as $medida) {
            MpAncho::firstOrCreate(['medida' => $medida]);
        }

        $colores = [
            ['nombre' => 'Azul Marino', 'codigo_hex' => '#000080'],
            ['nombre' => 'Negro',       'codigo_hex' => '#000000'],
            ['nombre' => 'Rojo Fuego',  'codigo_hex' => '#FF0000'],
            ['nombre' => 'Verde Oliva', 'codigo_hex' => '#556B2F'],
            ['nombre' => 'Crudo',       'codigo_hex' => '#F5F5DC'],
            ['nombre' => 'Gris Perla',  'codigo_hex' => '#E5E4E2'],
            ['nombre' => 'N/A',         'codigo_hex' => null],
        ];
        foreach ($colores as $c) {
            MpColor::firstOrCreate(['nombre' => $c['nombre']], $c);
        }

        // 2. PROVEEDOR DE EJEMPLO (Textil Las Américas)
        $proveedor = MpProveedor::firstOrCreate(
            ['rut_empresa' => '76.123.456-K'],
            [
                'nombre_fantasia' => 'Comercial Las Américas',
                'razon_social' => 'Importadora Textil Las Américas SpA',
                'contacto_nombre' => 'Juan Pérez',
                'telefono' => '+56912345678',
                'email' => 'ventas@tlasamericas.cl'
            ]
        );

        // 3. MATERIAL DE EJEMPLO (Tela Techni-Shell)
        $telaTipo = MpTipo::where('nombre', 'Tela')->first();
        $mtsUnidad = MpUnidad::where('abreviacion', 'Mts')->first();
        $ancho150 = MpAncho::where('medida', '1.50m')->first();
        $azulMarino = MpColor::where('nombre', 'Azul Marino')->first();

        $material = MpMaterial::firstOrCreate(
            ['codigo_interno' => 'TEL-TS-001'],
            [
                'nombre_base' => 'Techni-Shell Impermeable',
                'tipo_id' => $telaTipo->id,
                'unidad_id' => $mtsUnidad->id,
                'ancho_id' => $ancho150->id,
                'color_id' => $azulMarino->id,
                'stock_minimo' => 50,
                'descripcion' => 'Tela técnica para chaquetas de bomberos, resistente al agua.'
            ]
        );

        // Asociar Material con Proveedor (Tabla Pivote)
        // El syncWithoutDetaching evita duplicados si corres el seeder 2 veces
        $material->proveedores()->syncWithoutDetaching([
            $proveedor->id => [
                'sku_proveedor' => 'TS-NAVY-PRO',
                'precio_referencia' => 4500.00,
                'moneda' => 'CLP'
            ]
        ]);

        // 4. STOCK FÍSICO (Simulación de llegada de Factura con 3 rollos)
        // Factura #2024-99, Tinte Lote A-505
        
        $rollos = [
            ['codigo' => 'R-1001', 'cant' => 50.5],
            ['codigo' => 'R-1002', 'cant' => 48.0],
            ['codigo' => 'R-1003', 'cant' => 51.2],
        ];

        foreach ($rollos as $rollo) {
            MpLote::firstOrCreate(
                ['codigo_barra_unico' => $rollo['codigo']], 
                [
                    'material_id' => $material->id,
                    'codigo_lote_proveedor' => 'LOTE-TINTE-A505',
                    'factura_referencia' => 'FAC-2024-99',
                    'fecha_ingreso' => Carbon::now()->subDays(2), // Llegó hace 2 días
                    'cantidad_inicial' => $rollo['cant'],
                    'cantidad_actual' => $rollo['cant'], // Están llenos
                    'cantidad_reservada' => 0,
                    'ubicacion' => 'Estante A-01',
                    'estado' => 'DISPONIBLE'
                ]
            );
        }

        $this->command->info('¡Bodega sembrada con éxito! Catálogos y Stock de prueba creados.');
    }
}