<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CuttingOrder;
use App\Models\CuttingOrderItem;

class CuttingOrderQcSeeder extends Seeder
{
    /**
     * Seed órdenes de corte con datos de Control de Calidad
     */
    public function run(): void
    {
        $this->command->info('Creando órdenes de corte con Control de Calidad...');

        // Orden 1: En estado de Corte (sin QC iniciado)
        $order1 = CuttingOrder::create([
            'code' => '1001',
            'client' => 'Bomberos de Santiago',
            'selected_product' => 'Polera Polo Bomberos',
            'status' => 'cutting',
            'notes' => 'Orden inicial sin QC',
            'estimated_days' => 10,
            'progress' => 30,
            'total_unidades' => 100,
            'unidades_en_taller' => 100,
            'unidades_en_reparacion' => 0,
            'unidades_listas' => 0,
        ]);

        $order1->items()->create([
            'product_type' => 'Polera Polo',
            'size' => 'L',
            'color' => 'Azul',
            'quantity' => 50,
            'fabric_type' => 'Algodón',
        ]);

        $order1->items()->create([
            'product_type' => 'Polera Polo',
            'size' => 'XL',
            'color' => 'Azul',
            'quantity' => 50,
            'fabric_type' => 'Algodón',
        ]);

        // Orden 2: En Control de Calidad (progreso parcial)
        $order2 = CuttingOrder::create([
            'code' => '1002',
            'client' => 'Bomberos Quinta Compañía',
            'selected_product' => 'Polera Casual',
            'status' => 'quality-control',
            'notes' => 'Primera entrega recibida',
            'estimated_days' => 7,
            'progress' => 60,
            'total_unidades' => 80,
            'unidades_en_taller' => 32,
            'unidades_en_reparacion' => 8,
            'unidades_listas' => 40,
        ]);

        $order2->items()->create([
            'product_type' => 'Polera Casual',
            'size' => 'M',
            'color' => 'Rojo',
            'quantity' => 80,
            'fabric_type' => 'Poliéster',
        ]);

        // Orden 3: En Control de Calidad (casi completa)
        $order3 = CuttingOrder::create([
            'code' => '1003',
            'client' => 'Bomberos Segunda Compañía',
            'selected_product' => 'Polera Urbana',
            'status' => 'quality-control',
            'notes' => 'Faltan pocas unidades',
            'estimated_days' => 5,
            'progress' => 90,
            'total_unidades' => 50,
            'unidades_en_taller' => 5,
            'unidades_en_reparacion' => 3,
            'unidades_listas' => 42,
        ]);

        $order3->items()->create([
            'product_type' => 'Polera Urbana',
            'size' => 'S',
            'color' => 'Negro',
            'quantity' => 50,
            'fabric_type' => 'Algodón Premium',
        ]);

        // Orden 4: En Control de Calidad (COMPLETA - puede pasar a siguiente estado)
        $order4 = CuttingOrder::create([
            'code' => '1004',
            'client' => 'Bomberos Tercera Compañía',
            'selected_product' => 'Polera Polo Premium',
            'status' => 'quality-control',
            'notes' => 'QC completo - listo para finalizar',
            'estimated_days' => 8,
            'progress' => 100,
            'total_unidades' => 60,
            'unidades_en_taller' => 0,
            'unidades_en_reparacion' => 0,
            'unidades_listas' => 60,
        ]);

        $order4->items()->create([
            'product_type' => 'Polera Polo Premium',
            'size' => 'L',
            'color' => 'Azul Marino',
            'quantity' => 30,
            'fabric_type' => 'Algodón Peinado',
        ]);

        $order4->items()->create([
            'product_type' => 'Polera Polo Premium',
            'size' => 'XL',
            'color' => 'Azul Marino',
            'quantity' => 30,
            'fabric_type' => 'Algodón Peinado',
        ]);

        // Orden 5: Terminada (ejemplo de orden completada)
        $order5 = CuttingOrder::create([
            'code' => '1005',
            'client' => 'Bomberos Cuarta Compañía',
            'selected_product' => 'Polera Técnica',
            'status' => 'finished',
            'notes' => 'Orden completada exitosamente',
            'estimated_days' => 6,
            'progress' => 100,
            'total_unidades' => 40,
            'unidades_en_taller' => 0,
            'unidades_en_reparacion' => 0,
            'unidades_listas' => 40,
        ]);

        $order5->items()->create([
            'product_type' => 'Polera Técnica',
            'size' => 'M',
            'color' => 'Verde',
            'quantity' => 40,
            'fabric_type' => 'Techni-Shell',
        ]);

        // Orden 6: En Confección (aún no llega a QC)
        $order6 = CuttingOrder::create([
            'code' => '1006',
            'client' => 'Bomberos Sexta Compañía',
            'selected_product' => 'Polera Deportiva',
            'status' => 'sewing',
            'notes' => 'En proceso de confección',
            'estimated_days' => 12,
            'progress' => 45,
            'total_unidades' => 120,
            'unidades_en_taller' => 120,
            'unidades_en_reparacion' => 0,
            'unidades_listas' => 0,
        ]);

        $order6->items()->create([
            'product_type' => 'Polera Deportiva',
            'size' => 'L',
            'color' => 'Amarillo',
            'quantity' => 60,
            'fabric_type' => 'Dry-Fit',
        ]);

        $order6->items()->create([
            'product_type' => 'Polera Deportiva',
            'size' => 'XL',
            'color' => 'Amarillo',
            'quantity' => 60,
            'fabric_type' => 'Dry-Fit',
        ]);

        $this->command->info('✅ Se crearon 6 órdenes de corte con diferentes estados de QC');
        $this->command->info('   - Orden 1001: En Corte (0% QC)');
        $this->command->info('   - Orden 1002: En QC (50% completado) - 40/80 unidades');
        $this->command->info('   - Orden 1003: En QC (84% completado) - 42/50 unidades');
        $this->command->info('   - Orden 1004: En QC (100% completado) - Listo para finalizar');
        $this->command->info('   - Orden 1005: Terminado (100% completado)');
        $this->command->info('   - Orden 1006: En Confección (0% QC)');
    }
}
