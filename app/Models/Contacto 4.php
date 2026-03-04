<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    protected $fillable = [
        'cliente_id',
        'rut',
        'nombre',
        'telefono',
        'email',
        'cargo',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
