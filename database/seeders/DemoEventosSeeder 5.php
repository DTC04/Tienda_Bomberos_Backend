<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoEventosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Evento::create([
            'ejecutivo_id' => 1,
            'titulo' => 'Reunión General de Ventas',
            'descripcion' => 'Reunión obligatoria para todo el equipo comercial.',
            'inicio' => now()->format('Y-m-d') . ' 10:00:00',
            'fin' => now()->format('Y-m-d') . ' 11:30:00',
            'nivel' => 'corporativo',
            'tipo' => 'reunion'
        ]);

        \App\Models\Evento::create([
            'ejecutivo_id' => 1,
            'titulo' => 'Mantenimiento de Servidores',
            'descripcion' => 'Hito técnico de área.',
            'inicio' => now()->addDays(2)->format('Y-m-d') . ' 00:00:00',
            'fin' => now()->addDays(2)->format('Y-m-d') . ' 23:59:59',
            'nivel' => 'area',
            'area' => 'admin',
            'tipo' => 'seguimiento'
        ]);
    }
}
