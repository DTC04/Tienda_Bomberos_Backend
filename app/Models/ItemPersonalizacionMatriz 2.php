<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPersonalizacionMatriz extends Model
{
    use HasFactory;

    protected $table = 'item_personalizacion_matrices';

    protected $fillable = [
        'grupo_id',
        'posicion',
        'tecnica',
        'tipo_contenido',
        'valor_fijo',
        'color',
    ];

    public function grupo()
    {
        return $this->belongsTo(ItemPersonalizacionGrupo::class, 'grupo_id');
    }
}
