<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCotizacion extends Model
{
    protected $table = 'detalle_cotizaciones';

    protected $fillable = [
        'cotizacion_id',
        'sku',
        'n_item',
        'cantidad',
        'subtotal',
        'is_personalizable',
        'producto',
        'talla',
        'color',
        'genero',
        'tipo_personalizacion',
        'precio_unitario',
        'total_neto',
    ];

    protected $casts = [
        'is_personalizable' => 'boolean',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function gruposPersonalizacion()
    {
        return $this->hasMany(ItemPersonalizacionGrupo::class, 'cotizacion_item_id');
    }
}
