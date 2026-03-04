<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Despacho extends Model
{
    public function cotizacion()
{
    return $this->belongsTo(Cotizacion::class);
}

public function cliente()
{
    return $this->belongsTo(Cliente::class);
}
}
