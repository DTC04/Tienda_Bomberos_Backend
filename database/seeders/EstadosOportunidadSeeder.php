<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estado;

class EstadosOportunidadSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Nuevo',              'color' => '#111827', 'orden' => 10],
            ['nombre' => 'Contactado',         'color' => '#2563eb', 'orden' => 20],
            ['nombre' => 'En calificación',    'color' => '#7c3aed', 'orden' => 30],
            ['nombre' => 'Enviar cotización',  'color' => '#f59e0b', 'orden' => 40],
            ['nombre' => 'Cotización enviada', 'color' => '#0ea5e9', 'orden' => 50],
            ['nombre' => 'En negociación',     'color' => '#f97316', 'orden' => 60],
            ['nombre' => 'Cerrada ganada',     'color' => '#16a34a', 'orden' => 70],
            ['nombre' => 'Cerrada perdida',    'color' => '#dc2626', 'orden' => 80],
        ];

        foreach ($estados as $e) {
            Estado::updateOrCreate(
                ['scope' => 'oportunidad', 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden']]
            );
        }
    }
}
