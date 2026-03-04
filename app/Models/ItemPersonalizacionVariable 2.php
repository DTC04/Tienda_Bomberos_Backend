<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPersonalizacionVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'grupo_id',
        'talla',
        'valor_variable',
    ];

    public function grupo()
    {
        return $this->belongsTo(ItemPersonalizacionGrupo::class, 'grupo_id');
    }
}
