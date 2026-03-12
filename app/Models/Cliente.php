<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    // The primary key is now a string and not auto-incrementing
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', // Need to make it fillable since it's manually assigned
        'nombre_empresa',
        'rut_empresa',
        'logo_url',
        'fecha_fundacion',
        'direccion',
        'giro',
        'nombre_contacto',
        'rut_contacto',
        'cargo_contacto',
        'telefono',
        'correo',
        'superintendente',
        'comandante',
        'numero_companias',
        'fecha_ingreso',
        'region_id',
        'provincia_id',
        'parent_id',
        'cuerpo_id',
        'compania_id',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }

    public function parent()
    {
        return $this->belongsTo(Cliente::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Cliente::class, 'parent_id');
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class);
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }
}
