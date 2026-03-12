<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compania extends Model
{
    protected $table = 'companias';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'cuerpo_id',
        'nombre',
        'numero',
    ];

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'compania_id');
    }
}
