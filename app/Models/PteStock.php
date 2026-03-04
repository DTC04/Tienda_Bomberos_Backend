<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteStock extends Model
{
    use HasFactory;

    // Mantenemos tu tabla tal cual la tienes
    protected $table = 'pte_stocks';

    // Mantenemos los campos que usa tu StockController + el de alertas
    protected $fillable = ['sku', 'cantidad', 'reserved_stock', 'stock_critico'];

    /**
     * RELACIÓN CORREGIDA:
     * Le cambiamos el nombre de 'sku()' a 'skuProducto()'.
     * 1. Evita conflictos con la columna 'sku'.
     * 2. Coincide con lo que espera el AlertasController.
     */
    public function skuProducto()
    {
        // belongsTo(Modelo, 'foreign_key', 'owner_key')
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }
}