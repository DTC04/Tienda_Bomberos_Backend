<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

use App\Models\User;

// Modelos de fábrica con F_
use App\Models\F_MpMateriaPrima;
use App\Models\F_OrdenProduccion;
use App\Models\F_ControlCalidad;
use App\Models\F_Merma;
use App\Models\F_FichaTecnica;
use App\Models\PteSku;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Peticion;
use App\Models\PteStock;
use App\Models\MpTipo;
use App\Models\MpUnidad;
use App\Models\MpAncho;
use App\Models\MpColor;
use App\Models\MpProveedor;
use App\Models\MpMaterial;

class DatabaseCompleteSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando DatabaseCompleteSeeder...');

        // 1) Catálogos base (familias, subfamilias, colores, tallas, etc.)
        $this->call(CatalogosSeeder::class);
        $this->call(MpColoresSeeder::class);

        // 2) Usuarios demo (para login) -> se crean ANTES porque datos básicos los usan (user_id)
        $this->seedUsersDemo();

        // 3) Datos base (clientes, estados, oportunidad/cotización)
        $this->seedDatosBasicos();

        // 4) Materia Prima (Telas, Insumos, Proveedores)
        $this->seedMateriaPrima();

        // 5) SKUs + detalle cotización + peticiones
        $this->seedSKUsBasicos();

        // 5) Seed básico de fábrica (si existe en tu proyecto)
        // (Si no tienes FabricaSeeder, comenta esta línea)
        //$this->call(FabricaSeeder::class);

        // 6) Datos extendidos fábrica
        /*
        $this->seedOrdenesProduccionExtendidas();
        $this->seedControlCalidadExtendido();
        $this->seedMermasExtendidas();
        $this->seedFichasTecnicasExtendidas();
        */
        $this->command->info('DatabaseCompleteSeeder finalizado');
    }

    private function seedUsersDemo(): void
    {
        $this->command->info('Creando usuarios demo...');

        User::updateOrCreate(
            ['email' => 'admin@demo.cl'],
            [
                'name' => 'Admin Demo',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'comercial@demo.cl'],
            [
                'name' => 'Comercial Demo',
                'password' => Hash::make('password'),
                'role' => 'comercial',
            ]
        );

        User::updateOrCreate(
            ['email' => 'bodega@demo.cl'],
            [
                'name' => 'Bodega Demo',
                'password' => Hash::make('password'),
                'role' => 'bodega',
            ]
        );

        User::updateOrCreate(
            ['email' => 'fabrica@demo.cl'],
            [
                'name' => 'Fabrica Demo',
                'password' => Hash::make('password'),
                'role' => 'fabrica',
            ]
        );

        User::updateOrCreate(
            ['email' => 'personalizacion@demo.cl'],
            [
                'name' => 'Personalizacion Demo',
                'password' => Hash::make('password'),
                'role' => 'personalizacion',
            ]
        );

        User::updateOrCreate(
            ['email' => 'inventario@demo.cl'],
            [
                'name' => 'Inventario Demo',
                'password' => Hash::make('password'),
                'role' => 'inventario',
            ]
        );
    }

    private function seedDatosBasicos(): void
    {
        $this->command->info('Creando datos básicos (clientes, estados, oportunidad/cotización)...');

        if (Cliente::count() === 0) {
            Cliente::create([
                'nombre_empresa' => 'Bomberos de Santiago',
                'nombre_contacto' => 'Carlos Morales',
                'telefono' => '+56923456789',
                'correo' => 'contacto@bomberossantiago.cl',
                'fecha_ingreso' => '2023-01-15',
            ]);
        }

        // Estados por scope: oportunidad y cotizacion
        $estadosOportunidad = [
            ['nombre' => 'Nuevo', 'color' => 'blue', 'scope' => 'oportunidad', 'orden' => 1, 'next_estado_id' => null],
            ['nombre' => 'Contactado', 'color' => 'yellow', 'scope' => 'oportunidad', 'orden' => 2, 'next_estado_id' => null],
            ['nombre' => 'Esperando Respuesta', 'color' => 'grey', 'scope' => 'oportunidad', 'orden' => 3, 'next_estado_id' => null],
            ['nombre' => 'Aprobada - Cotización', 'color' => 'green', 'scope' => 'oportunidad', 'orden' => 4, 'next_estado_id' => null],
            ['nombre' => 'Rechazada', 'color' => 'red', 'scope' => 'oportunidad', 'orden' => 5, 'next_estado_id' => null],
        ];

        $estadosCotizacion = [
            ['nombre' => 'Cotización Enviada', 'color' => 'yellow', 'scope' => 'cotizacion', 'orden' => 1, 'next_estado_id' => null],
            ['nombre' => 'Ventas', 'color' => 'green', 'scope' => 'cotizacion', 'orden' => 2, 'next_estado_id' => null],
            ['nombre' => 'Rechazado', 'color' => 'red', 'scope' => 'cotizacion', 'orden' => 3, 'next_estado_id' => null],
        ];

        $estadosPeticion = [
            ['nombre' => 'Pendiente', 'color' => 'blue', 'scope' => 'peticion', 'orden' => 1, 'next_estado_id' => null],
            ['nombre' => 'Despachado', 'color' => 'green', 'scope' => 'peticion', 'orden' => 2, 'next_estado_id' => null],
            ['nombre' => 'Anulado', 'color' => 'red', 'scope' => 'peticion', 'orden' => 3, 'next_estado_id' => null],
        ];

        foreach ($estadosOportunidad as $e) {
            Estado::updateOrCreate(
                ['scope' => $e['scope'], 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }

        foreach ($estadosCotizacion as $e) {
            Estado::updateOrCreate(
                ['scope' => $e['scope'], 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }

        foreach ($estadosPeticion as $e) {
            Estado::updateOrCreate(
                ['scope' => $e['scope'], 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }

        // Buscar estados por scope para asignarlos correctamente
        $estadoOportunidadNuevoId = Estado::where('scope', 'oportunidad')->where('nombre', 'Nuevo')->value('id');
        // Usamos Cotización Enviada como default
        $estadoCotizacionDefaultId = Estado::where('scope', 'cotizacion')->where('nombre', 'Cotización Enviada')->value('id');

        // Usuario comercial demo para referenciar user_id
        $comercialUserId = User::where('email', 'comercial@demo.cl')->value('id') ?? User::first()?->id;

        if (Oportunidad::count() === 0) {
            Oportunidad::create([
                'fecha_ingreso' => Carbon::now()->toDateString(),
                'nombre_contacto' => 'Contacto de Prueba',
                'numero_contacto' => '+56900000000',
                'empresa' => 'Empresa de Prueba',
                'cliente_id' => Cliente::first()->id,
                'user_id' => $comercialUserId,
                'estado_id' => $estadoOportunidadNuevoId ?? Estado::where('scope', 'oportunidad')->first()->id,
            ]);
        }

        if (Cotizacion::count() === 0) {
            Cotizacion::create([
                'oportunidad_id' => Oportunidad::first()->id,
                'cliente_id' => Cliente::first()->id,
                'user_id' => $comercialUserId,
                'fecha_creacion' => Carbon::now()->toDateString(),
                'fecha_vencimiento' => Carbon::now()->addDays(30)->toDateString(),
                'observaciones' => 'Cotización de prueba',
                // Muy importante: estado de cotizacion, no el de oportunidad
                'estado_id' => $estadoCotizacionDefaultId ?? Estado::where('scope', 'cotizacion')->first()->id,
                'origen' => 'manual',
            ]);
        }
    }

    private function seedSKUsBasicos(): void
    {
        $this->command->info('Creando SKUs básicos...');

        // Lista de productos solicitada por el usuario (Precio y SKU específico)
        $productos = [
            ['sku' => '111220310113111', 'nombre' => 'Polera Casual Hombre Bomberos', 'precio' => 14990],
            ['sku' => '111220310123111', 'nombre' => 'Polera Casual Mujer Bomberos', 'precio' => 14990],
            ['sku' => '111220310210012', 'nombre' => 'Polera Casual Hombre Bomberos', 'precio' => 15990],
            ['sku' => '111220310220012', 'nombre' => 'Polera Casual Mujer Bomberos', 'precio' => 15990],
            ['sku' => '111220300131014', 'nombre' => 'Polera Polo Unisex Bomberos', 'precio' => 10990],
            ['sku' => '111220300239011', 'nombre' => 'Polera Polo Unisex Bomberos', 'precio' => 14990],
            ['sku' => '111220340136013', 'nombre' => 'Polera Urbana Unisex Bomberos', 'precio' => 9990],
            ['sku' => '161220180133117', 'nombre' => 'Polera Polo Bomberos Octava', 'precio' => 12990],

            // Polera Polo Bomberos Segunda
            ['sku' => '161220120133113', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],
            ['sku' => '161220120133114', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],
            ['sku' => '161220120133115', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],
            ['sku' => '161220120133116', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],
            ['sku' => '161220120133117', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],
            ['sku' => '161220120133118', 'nombre' => 'Polera Polo Bomberos Segunda', 'precio' => 12990],

            // Polera Polo Bomberos Tercera
            ['sku' => '161220130133113', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],
            ['sku' => '161220130133114', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],
            ['sku' => '161220130133115', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],
            ['sku' => '161220130133116', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],
            ['sku' => '161220130133117', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],
            ['sku' => '161220130133118', 'nombre' => 'Polera Polo Bomberos Tercera', 'precio' => 12990],

            // Polera Polo Bomberos Cuarta
            ['sku' => '161220140133113', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],
            ['sku' => '161220140133114', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],
            ['sku' => '161220140133115', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],
            ['sku' => '161220140133116', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],
            ['sku' => '161220140133117', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],
            ['sku' => '161220140133118', 'nombre' => 'Polera Polo Bomberos Cuarta', 'precio' => 12990],

            // Polera Polo Bomberos Quinta
            ['sku' => '161220150133113', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],
            ['sku' => '161220150133114', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],
            ['sku' => '161220150133115', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],
            ['sku' => '161220150133116', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],
            ['sku' => '161220150133117', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],
            ['sku' => '161220150133118', 'nombre' => 'Polera Polo Bomberos Quinta', 'precio' => 12990],

            // Polera Polo Bomberos Sexta
            ['sku' => '161220160133113', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
            ['sku' => '161220160133114', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
            ['sku' => '161220160133115', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
            ['sku' => '161220160133116', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
            ['sku' => '161220160133117', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
            ['sku' => '161220160133118', 'nombre' => 'Polera Polo Bomberos Sexta', 'precio' => 12990],
        ];

        $cotizacion = Cotizacion::first();
        if (!$cotizacion) {
            $this->command->warn('No existe cotización, se omite seed de SKUs y dependencias.');
            return;
        }
        $cotizacionId = $cotizacion->id;

        foreach ($productos as $p) {
            $sku = $p['sku'];

            // Desglose lógica SKU (15 dígitos):
            // U(1) O(1) G(1) F(1) S(2) T(2) Fo(2) Ge(1) Co(2) Ta(2)
            $u = (int) substr($sku, 0, 1);
            $o = (int) substr($sku, 1, 1);
            $g = (int) substr($sku, 2, 1);
            $f = (int) substr($sku, 3, 1);
            $s = (int) substr($sku, 4, 2);
            $t = (int) substr($sku, 6, 2);
            $fo = (int) substr($sku, 8, 2);
            $ge = (int) substr($sku, 10, 1);
            $co = (int) substr($sku, 11, 2);
            $ta = (int) substr($sku, 13, 2);

            // 1. Crear/Actualizar el SKU en pte_skus
            PteSku::updateOrCreate(
                ['sku' => $sku],
                [
                    'nombre' => $p['nombre'],
                    'descripcion' => $p['nombre'] . ' - Producto Demo',
                    'precio_venta' => $p['precio'],
                    'stock_critico' => 5,
                    'unidad_negocio_id' => $u,
                    'origen_id' => $o,
                    'grp_familia_id' => $g,
                    'familia_id' => $f,
                    'subfamilia_id' => $s,
                    'familia_tipo_id' => $t,
                    'familia_formato_id' => $fo,
                    'genero_id' => $ge,
                    'color_id' => $co,
                    'talla_id' => $ta,
                ]
            );

            // 2. Iniciar Stock en 0 (si no existe)
            PteStock::updateOrCreate(
                ['sku' => $sku],
                [
                    'cantidad' => 0,
                    'stock_critico' => 5
                ]
            );

            // 3. Asociar a Cotización (Solo agregamos unos pocos al detalle para no saturar)
            if (DetalleCotizacion::where('sku', $sku)->count() == 0 && DetalleCotizacion::count() < 3) {
                $nItem = DetalleCotizacion::where('cotizacion_id', $cotizacionId)->count() + 1;
                DetalleCotizacion::create([
                    'cotizacion_id' => $cotizacionId,
                    'sku' => $sku,
                    'n_item' => $nItem,
                    'cantidad' => 10,
                    'subtotal' => $p['precio'] * 10,
                    'is_personalizable' => false,
                ]);
            }
        }

        $this->seedPeticiones();
    }

    private function seedMateriaPrima(): void
    {
        $this->command->info('Creando datos de Materia Prima (MP)...');

        // 1. Tipos de Material
        $tipoTela = MpTipo::updateOrCreate(['nombre' => 'Tela']);
        $tipoInsumo = MpTipo::updateOrCreate(['nombre' => 'Insumo']);

        // 2. Unidades
        $unitMetros = MpUnidad::updateOrCreate(['nombre' => 'Metros'], ['abreviacion' => 'Mts']);
        $unitUnidad = MpUnidad::updateOrCreate(['nombre' => 'Unidades'], ['abreviacion' => 'Ud']);

        // 3. Anchos
        $ancho150 = MpAncho::updateOrCreate(['medida' => '1.50m'], ['medida' => '1.50m']);
        $anchoNA = MpAncho::updateOrCreate(['medida' => 'N/A'], ['medida' => 'N/A']);

        // 4. Proveedor solicitado: INVERSIONES R Y S
        $proveedor = MpProveedor::updateOrCreate(
            ['rut_empresa' => '76.176.853-0'],
            [
                'nombre_fantasia' => 'INVERSIONES R Y S',
                'razon_social' => 'INVERSIONES R Y S SPA',
                'contacto_nombre' => 'Darwin Chaparro Araneda',
                'telefono' => '999788360',
                'email' => 'DARWINCHAP@MAIL.COM'
            ]
        );

        // 5. Colores (Buscamos los creados por MpColoresSeeder)
        $colorAzul = MpColor::where('nombre', 'Azul')->first();
        $colorVerde = MpColor::where('nombre', 'Verde')->first();
        $colorGenerico = MpColor::where('nombre', 'Generico')->first();

        // 6. Materiales (Telas e Insumos)
        $materiales = [
            [
                'codigo_interno' => 'TEL-TS-001',
                'nombre_base' => 'Techni-Shell Impermeable',
                'tipo_id' => $tipoTela->id,
                'unidad_id' => $unitMetros->id,
                'ancho_id' => $ancho150->id,
                'color_id' => $colorAzul?->id ?? 1,
                'descripcion' => 'Tela Techni-Shell Impermeable de prueba'
            ],
            [
                'codigo_interno' => 'GAM-001',
                'nombre_base' => 'Tela Gamuza',
                'tipo_id' => $tipoTela->id,
                'unidad_id' => $unitMetros->id,
                'ancho_id' => $ancho150->id,
                'color_id' => $colorVerde?->id ?? 1,
                'descripcion' => 'Tela Gamuza de prueba'
            ],
            [
                'codigo_interno' => 'YKK-001',
                'nombre_base' => 'CIERRE YKK PLATEADO',
                'tipo_id' => $tipoInsumo->id,
                'unidad_id' => $unitUnidad->id,
                'ancho_id' => $anchoNA->id,
                'color_id' => $colorGenerico?->id ?? 1,
                'descripcion' => 'Cierre YKK Plateado'
            ],
            [
                'codigo_interno' => 'BTN-001',
                'nombre_base' => 'BOTON',
                'tipo_id' => $tipoInsumo->id,
                'unidad_id' => $unitUnidad->id,
                'ancho_id' => $anchoNA->id,
                'color_id' => $colorGenerico?->id ?? 1,
                'descripcion' => 'Boton de prueba'
            ],
        ];

        foreach ($materiales as $m) {
            $material = MpMaterial::updateOrCreate(
                ['codigo_interno' => $m['codigo_interno']],
                array_merge($m, ['stock_minimo' => 10, 'activo' => true])
            );

            // Asociar al proveedor
            $material->proveedores()->syncWithoutDetaching([
                $proveedor->id => [
                    'sku_proveedor' => 'SKU-' . $m['codigo_interno'],
                    'precio_referencia' => rand(1000, 5000),
                    'moneda' => 'CLP'
                ]
            ]);
        }
    }

    private function seedPeticiones(): void
    {
        $this->command->info('Creando peticiones...');

        $detalles = DetalleCotizacion::all();

        // Peticion usa estados de su propio scope? Si no existe, usa el primer estado disponible
        // Peticion usa su propio scope
        $estadoId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id')
            ?? Estado::first()->id;

        foreach ($detalles as $detalle) {
            $exists = Peticion::where('detalle_cotizacion_id', $detalle->id)->exists();
            if ($exists)
                continue;

            Peticion::create([
                'detalle_cotizacion_id' => $detalle->id,
                'estado_id' => $estadoId,
                'fecha_creacion' => Carbon::now()->toDateString(),
                'fecha_vencimiento' => Carbon::now()->addDays(15)->toDateString(),
                'observacion' => 'Petición para SKU: ' . ($detalle->sku ?? '(sin sku)'),
            ]);
        }
    }

    private function seedOrdenesProduccionExtendidas(): void
    {
        $this->command->info('Poblando órdenes de producción...');

        $peticiones = Peticion::with('detalleCotizacion')->get();
        $estadosProduccion = ['Pendiente', 'En Proceso', 'Completada', 'Cancelada'];

        foreach ($peticiones as $peticion) {
            $exists = F_OrdenProduccion::where('peticion_id', $peticion->id)->exists();
            if ($exists)
                continue;

            $sku = $peticion->detalleCotizacion?->sku ?? null;
            if (!$sku)
                continue;

            F_OrdenProduccion::create([
                'peticion_id' => $peticion->id,
                'sku' => $sku,
                'cantidad_a_producir' => rand(20, 100),
                'estado_produccion' => $estadosProduccion[array_rand($estadosProduccion)],
            ]);
        }
    }

    private function seedControlCalidadExtendido(): void
    {
        $this->command->info('Poblando control de calidad...');

        $ordenes = F_OrdenProduccion::whereIn('estado_produccion', ['Completada', 'En Proceso'])->get();

        // Inspector: usuario de fábrica demo
        $inspectorId = User::where('email', 'fabrica@demo.cl')->value('id') ?? User::first()?->id;

        foreach ($ordenes as $orden) {
            $exists = F_ControlCalidad::where('orden_produccion_id', $orden->id)->exists();
            if ($exists)
                continue;

            if (rand(1, 10) <= 8) {
                $cantidadTotal = (int) $orden->cantidad_a_producir;
                $porcentajeAprobacion = rand(75, 98);
                $cantidadAprobada = (int) floor($cantidadTotal * ($porcentajeAprobacion / 100));
                $cantidadRechazada = $cantidadTotal - $cantidadAprobada;

                $observaciones = [
                    'Control estándar - Calidad aceptable',
                    'Lote con alta calidad - Sin observaciones',
                    'Algunas unidades con defectos menores',
                    'Control riguroso aplicado - Resultados satisfactorios',
                    'Revisión detallada de acabados y costuras',
                    'Control de resistencia y durabilidad aprobado',
                ];

                F_ControlCalidad::create([
                    'orden_produccion_id' => $orden->id,
                    'fecha_inspeccion' => Carbon::now()->subDays(rand(1, 30))->toDateString(),
                    'cantidad_aprobada' => $cantidadAprobada,
                    'cantidad_rechazada' => $cantidadRechazada,
                    'inspector_id' => $inspectorId ?? 1,
                    'observaciones' => $observaciones[array_rand($observaciones)],
                ]);
            }
        }
    }

    private function seedMermasExtendidas(): void
    {
        $this->command->info('Poblando mermas...');

        $motivos = [
            'Material defectuoso en origen',
            'Error en proceso de corte',
            'Daño durante transporte interno',
            'Desgaste normal de maquinaria',
            'Ajuste de patrón de corte',
        ];

        $controles = F_ControlCalidad::where('cantidad_rechazada', '>', 0)->get();
        $materiasPrimas = F_MpMateriaPrima::pluck('id')->toArray();

        foreach ($controles as $control) {
            if (empty($materiasPrimas))
                continue;

            $exists = F_Merma::where('control_calidad_id', $control->id)->exists();
            if ($exists)
                continue;

            if (rand(1, 10) <= 6) {
                F_Merma::create([
                    'control_calidad_id' => $control->id,
                    'orden_produccion_id' => $control->orden_produccion_id,
                    'materia_prima_id' => $materiasPrimas[array_rand($materiasPrimas)],
                    'cantidad_perdida' => rand(1, 25),
                    'motivo_merma' => $motivos[array_rand($motivos)],
                ]);
            }
        }
    }

    private function seedFichasTecnicasExtendidas(): void
    {
        $this->command->info('Poblando fichas técnicas...');

        $skus = ['10101010', '20202020', '30303030', '40404040', '50505050'];
        $materiasPrimasIds = F_MpMateriaPrima::pluck('id')->toArray();

        if (empty($materiasPrimasIds))
            return;

        foreach ($skus as $sku) {
            if (!PteSku::where('sku', $sku)->exists())
                continue;

            $numMateriales = rand(2, 3);
            $indices = array_rand($materiasPrimasIds, min($numMateriales, count($materiasPrimasIds)));
            if (!is_array($indices))
                $indices = [$indices];

            foreach ($indices as $i) {
                $mpId = $materiasPrimasIds[$i];

                $exists = F_FichaTecnica::where('sku', $sku)->where('materia_prima_id', $mpId)->exists();
                if ($exists)
                    continue;

                F_FichaTecnica::create([
                    'sku' => $sku,
                    'materia_prima_id' => $mpId,
                    'cantidad_requerida' => rand(500, 3000) / 1000,
                ]);
            }
        }
    }
}
