<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'oportunidad_id',
        'cliente_id',
        'user_id',
        'estado_id',
        'numero',
        'nombre',
        'fecha_creacion',
        'fecha_vencimiento',
        'observaciones',
        'total_neto',
        'iva',
        'total',
        'plazo_produccion',
        'condiciones_pago',
        'despacho',
        'personalizacion_completada',
        'origen',
        'fecha_rechazo',
        'motivo_rechazo',
        'estado_personalizacion',
    ];

    protected $casts = [
        'fecha_creacion' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_rechazo' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class, 'cotizacion_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function oportunidad()
    {
        return $this->belongsTo(Oportunidad::class, 'oportunidad_id');
    }

    public function ejecutivo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }
    public function historial()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user', 'estadoAnterior', 'estadoNuevo'])
            ->latest();
    }

    public function archivos()
    {
        return $this->hasMany(CotizacionArchivo::class, 'cotizacion_id');
    }

    public function gestiones()
    {
        return $this->hasMany(CotizacionGestion::class)->orderByDesc('fecha_gestion');
    }

    public function ultimaGestion()
    {
        return $this->hasOne(CotizacionGestion::class)->latestOfMany();
    }
}
