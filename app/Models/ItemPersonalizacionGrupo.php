<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPersonalizacionGrupo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_item_id',
        'nombre_grupo',
        'archivo_adjunto',
    ];

    public function item()
    {
        return $this->belongsTo(DetalleCotizacion::class, 'cotizacion_item_id');
    }

    public function tallas()
    {
        return $this->hasMany(ItemPersonalizacionTalla::class, 'grupo_id');
    }

    public function matrices()
    {
        return $this->hasMany(ItemPersonalizacionMatriz::class, 'grupo_id');
    }

    public function variables()
    {
        return $this->hasMany(ItemPersonalizacionVariable::class, 'grupo_id');
    }
}
