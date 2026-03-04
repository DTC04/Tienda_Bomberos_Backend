<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteSku extends Model
{
    use HasFactory;

    // 1. CONFIGURACIÓN DE LA TABLA
    protected $table = 'pte_skus';      // Nombre exacto en la BD
    protected $primaryKey = 'sku';      // La llave no es 'id', es 'sku'
    public $incrementing = false;       // El SKU no es autoincrementable (1,2,3...)
    protected $keyType = 'string';      // El SKU es texto (para respetar ceros a la izquierda)
    protected $guarded = [];            // Permitimos guardar todos los campos sin restricciones

    // 2. RELACIÓN CON EL INVENTARIO (BODEGA)
    // Un Producto tiene UN registro de stock
    public function stock()
    {
        return $this->hasOne(PteStock::class, 'sku', 'sku');
    }

    // Un Producto tiene MUCHOS movimientos en su historia
    public function movimientos()
    {
        return $this->hasMany(PteMovimiento::class, 'sku', 'sku');
    }

    // 3. RELACIONES CON LOS CATÁLOGOS (INGREDIENTES)
    // Aquí le decimos a Laravel cómo traducir los IDs a Nombres
    
    public function unidadNegocio()
    {
        return $this->belongsTo(PteUnidadNegocio::class, 'unidad_negocio_id');
    }

    public function origen()
    {
        return $this->belongsTo(PteOrigen::class, 'origen_id');
    }

    public function grpFamilia()
    {
        return $this->belongsTo(PteGrpFamilia::class, 'grp_familia_id');
    }

    public function familia()
    {
        return $this->belongsTo(PteFamilia::class, 'familia_id');
    }

    public function subfamilia()
    {
        return $this->belongsTo(PteSubfamilia::class, 'subfamilia_id');
    }

    public function familiaTipo()
    {
        return $this->belongsTo(PteFamiliaTipo::class, 'familia_tipo_id');
    }

    public function familiaFormato()
    {
        return $this->belongsTo(PteFamiliaFormato::class, 'familia_formato_id');
    }

    public function genero()
    {
        return $this->belongsTo(PteGenero::class, 'genero_id');
    }

    public function color()
    {
        return $this->belongsTo(PteColor::class, 'color_id');
    }

    public function talla()
    {
        return $this->belongsTo(PteTalla::class, 'talla_id');
    }
}