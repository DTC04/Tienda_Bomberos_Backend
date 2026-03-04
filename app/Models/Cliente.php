<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
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
}
