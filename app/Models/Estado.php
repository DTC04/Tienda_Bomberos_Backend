<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $fillable = [
        'nombre',
        'color',
        'scope',
        'orden',
        'next_estado_id',
    ];
}

