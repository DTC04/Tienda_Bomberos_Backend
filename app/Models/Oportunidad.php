<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Oportunidad extends Model
{
    protected $table = 'oportunidades';

    protected $fillable = [
        'fecha_ingreso',
        'nombre_contacto',
        'numero_contacto',
        'empresa',
        'referido_por',
        'cliente_id',
        'contacto_id',
        'ejecutivo_id',
        'user_id',
        'estado_id',
    ];


    protected $casts = [
        'fecha_ingreso' => 'date',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ejecutivo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function gestiones()
    {
        return $this->hasMany(OportunidadGestion::class);
    }

    public function ultima_gestion()
    {
        return $this->hasOne(OportunidadGestion::class)->latestOfMany('fecha_gestion');
    }

    public function historial()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user', 'estadoAnterior', 'estadoNuevo'])
            ->latest();
    }
}
