<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPersonalizacionTalla extends Model
{
    use HasFactory;

    protected $fillable = [
        'grupo_id',
        'talla',
        'cantidad',
    ];

    public function grupo()
    {
        return $this->belongsTo(ItemPersonalizacionGrupo::class, 'grupo_id');
    }
}
