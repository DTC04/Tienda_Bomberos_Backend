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
<<<<<<<< HEAD:backend/database/migrations/2026_02_27_061420_add_foreign_key_to_pte_movimientos_table.php
        Schema::table('pte_movimientos', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
========
        Schema::table('item_personalizacion_grupos', function (Blueprint $table) {
            $table->string('archivo_adjunto')->nullable()->after('nombre_grupo');
>>>>>>>> 18532ddf (feat: modulo personalizacion, extraer logos y correcciones en kanban/cotizaciones):backend/database/migrations/2026_03_03_220612_add_archivo_adjunto_to_item_personalizacion_grupos_table.php
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
<<<<<<<< HEAD:backend/database/migrations/2026_02_27_061420_add_foreign_key_to_pte_movimientos_table.php
        Schema::table('pte_movimientos', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
========
        Schema::table('item_personalizacion_grupos', function (Blueprint $table) {
            $table->dropColumn('archivo_adjunto');
>>>>>>>> 18532ddf (feat: modulo personalizacion, extraer logos y correcciones en kanban/cotizaciones):backend/database/migrations/2026_03_03_220612_add_archivo_adjunto_to_item_personalizacion_grupos_table.php
        });
    }
};
