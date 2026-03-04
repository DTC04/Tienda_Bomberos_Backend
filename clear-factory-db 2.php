<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Limpiando base de datos de fábrica...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

$tables = [
    'logs_fabrica',
    'items_paquetes',
    'paquetes_ordenes_corte', // Si existe
    'paquetes',
    'suministros_ordenes_corte',
    'items_ordenes_corte',
    'ordenes_corte',
];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo "Tabla truncada: $table\n";
    } else {
        echo "Tabla no encontrada (saltada): $table\n";
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "¡Limpieza completada!\n";
