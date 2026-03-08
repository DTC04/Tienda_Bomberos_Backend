<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            CotizacionTemporalSeeder::class,
            MpColoresSeeder::class,
            MpCatalogosSeeder::class,
            InsumosSeeder::class,
            EstadosSeeder::class,
            AdminUserSeeder::class,
            RedBomberosSeeder::class,
        ]);
    }
}
