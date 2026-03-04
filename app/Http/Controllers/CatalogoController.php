<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// MODELOS
use App\Models\PteUnidadNegocio;
use App\Models\PteOrigen;
use App\Models\PteGrpFamilia;
use App\Models\PteFamilia;
use App\Models\PteSubfamilia;
use App\Models\PteFamiliaTipo;
use App\Models\PteFamiliaFormato;
use App\Models\PteGenero;
use App\Models\PteColor;
use App\Models\PteTalla;

class CatalogoController extends Controller
{
    /**
     * Unidades de negocio
     */
    public function unidades()
    {
        return Cache::remember('catalogos.unidades', now()->addHours(12), function () {
            return PteUnidadNegocio::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Orígenes
     */
    public function origenes()
    {
        return Cache::remember('catalogos.origenes', now()->addHours(12), function () {
            return PteOrigen::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Grupos de familia (endpoint legacy)
     * Mantiene compatibilidad con rutas antiguas
     */
    public function gruposFamilia()
    {
        return $this->grupos();
    }

    /**
     * Grupos de familia
     */
    public function grupos()
    {
        return Cache::remember('catalogos.grupos', now()->addHours(12), function () {
            return PteGrpFamilia::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Familias
     */
    public function familias()
    {
        return Cache::remember('catalogos.familias', now()->addHours(12), function () {
            return PteFamilia::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Subfamilias
     */
    public function subfamilias()
    {
        return Cache::remember('catalogos.subfamilias', now()->addHours(12), function () {
            return PteSubfamilia::select('id', 'nombre', 'familia_id')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Tipos de familia
     */
    public function tipos()
    {
        return Cache::remember('catalogos.tipos', now()->addHours(12), function () {
            return PteFamiliaTipo::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Formatos de familia
     */
    public function formatos()
    {
        return Cache::remember('catalogos.formatos', now()->addHours(12), function () {
            return PteFamiliaFormato::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Géneros
     */
    public function generos()
    {
        return Cache::remember('catalogos.generos', now()->addHours(12), function () {
            return PteGenero::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Colores
     */
    public function colores()
    {
        return Cache::remember('catalogos.colores', now()->addHours(12), function () {
            return PteColor::select('id', 'nombre')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Tallas
     */
    public function tallas()
    {
        return Cache::remember('catalogos.tallas', now()->addHours(12), function () {
            return PteTalla::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Obtener TODOS los catálogos en una sola petición.
     * Optimiza la carga del frontend evitando 10 requests simultáneos.
     */
    public function index()
    {
        return response()->json([
            'unidades' => $this->unidades(),
            'origenes' => $this->origenes(),
            'grupos' => $this->grupos(),
            'familias' => $this->familias(),
            'subfamilias' => $this->subfamilias(),
            'tipos' => $this->tipos(),
            'formatos' => $this->formatos(),
            'generos' => $this->generos(),
            'colores' => $this->colores(),
            'tallas' => $this->tallas(),
        ]);
    }
}
