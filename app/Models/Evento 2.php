<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $guarded = [];

    // Relación con el ejecutivo (Dueño del calendario)
    public function ejecutivo() {
        return $this->belongsTo(User::class, 'ejecutivo_id');
    }

    // Relación para hacer clic en el calendario e ir a la oportunidad
    public function oportunidad() {
        return $this->belongsTo(Oportunidad::class);
    }
}