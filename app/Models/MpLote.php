<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpLote extends Model
{
    use HasFactory;

    protected $table = 'mp_lotes';

    protected $fillable = [
        'material_id',
        'proveedor_id',         // <--- TRAZABILIDAD
        'codigo_barra_unico',    // R-1001
        'codigo_lote_proveedor', // Tinte Batch A
        'factura_referencia',
        'fecha_ingreso',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'cantidad_reservada',    // <--- CLAVE PARA FÁBRICA
        'ubicacion',
        'estado' // DISPONIBLE, AGOTADO, CUARENTENA
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
        'cantidad_actual' => 'decimal:2',
        'cantidad_reservada' => 'decimal:2',
    ];

    // Relación padre
    public function material()
    {
        return $this->belongsTo(MpMaterial::class, 'material_id');
    }

    // Proveedor de origen
    public function proveedor()
    {
        return $this->belongsTo(MpProveedor::class, 'proveedor_id');
    }

    // Historial
    public function movimientos()
    {
        return $this->hasMany(MpMovimiento::class, 'lote_id');
    }

    // --- LÓGICA DE NEGOCIO ---

    // ¿Cuánto puedo usar realmente? (Físico - Reservado)
    public function getDisponibleAttribute()
    {
        return max(0, $this->cantidad_actual - $this->cantidad_reservada);
    }
}
?>