<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuerpo extends Model
{
    protected $table = 'cuerpos';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'region_id',
        'nombre',
        'numero_socio',
        'rut',
        'fecha_fundacion',
        'direccion',
        'telefono',
        'superintendente',
        'comandante',
        'numero_companias',
        'logo_url',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function companias()
    {
        return $this->hasMany(Compania::class, 'cuerpo_id');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'cuerpo_id');
    }
}
