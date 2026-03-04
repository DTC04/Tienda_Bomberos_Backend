<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personalizacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'personalizaciones';

    protected $fillable = [
        'pte_movimiento_id',
        'user_id',
        'sku',
        'producto_nombre',
        'cantidad',
        'estado',
        'tipo',
        'configuracion',
        'notas',
        'prioridad',
        'cotizacion_id'
    ];

    protected $casts = [
        'configuracion' => 'array',
        'cantidad' => 'integer'
    ];

    public function pteMovimiento()
    {
        return $this->belongsTo(PteMovimiento::class, 'pte_movimiento_id');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Helper para obtener URL de silueta según tipo de producto (lógica simple por nombre o SKU)
    public function getSiluetaAttribute()
    {
        // Esto se podría mejorar con un mapa real de SKUs a siluetas
        $nombre = strtolower($this->producto_nombre);
        if (str_contains($nombre, 'polera'))
            return 't-shirt';
        if (str_contains($nombre, 'poleron') || str_contains($nombre, 'polerón'))
            return 'hoodie';
        if (str_contains($nombre, 'pantalon') || str_contains($nombre, 'pantalón'))
            return 'pants';
        return 'other';
    }
}
