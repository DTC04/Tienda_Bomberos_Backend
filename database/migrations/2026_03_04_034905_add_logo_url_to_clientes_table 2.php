<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
<<<<<<<< HEAD:backend/database/migrations/2026_02_27_071826_add_activo_to_pte_skus_table.php
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('sku');
========
        Schema::table('clientes', function (Blueprint $table) {
            $table->text('logo_url')->nullable()->after('rut_empresa');
>>>>>>>> 18532ddf (feat: modulo personalizacion, extraer logos y correcciones en kanban/cotizaciones):backend/database/migrations/2026_03_04_034905_add_logo_url_to_clientes_table.php
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
<<<<<<<< HEAD:backend/database/migrations/2026_02_27_071826_add_activo_to_pte_skus_table.php
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->dropColumn('activo');
========
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('logo_url');
>>>>>>>> 18532ddf (feat: modulo personalizacion, extraer logos y correcciones en kanban/cotizaciones):backend/database/migrations/2026_03_04_034905_add_logo_url_to_clientes_table.php
        });
    }
};
