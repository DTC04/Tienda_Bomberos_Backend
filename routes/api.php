<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UserController;

// Módulo PTE / Inventario

use App\Http\Controllers\StockController;
use App\Http\Controllers\SkuController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\MovimientoController;

// Módulo Comercial
use App\Http\Controllers\OportunidadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\Api\EstadoController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\Api\OrderRequestController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ProveedorController;

//Módulo Fábrica

use App\Http\Controllers\F_OrdenProduccionController;
use App\Http\Controllers\F_ControlCalidadController;
use App\Http\Controllers\F_ReparacionController;
use App\Http\Controllers\F_MpMateriaPrimaController;
use App\Http\Controllers\F_MpProveedorController;
use App\Http\Controllers\F_FichaTecnicaController;
use App\Http\Controllers\F_MpEspecificacionController;
use App\Http\Controllers\F_MpStockController;
use App\Http\Controllers\MpMaterialController;
use App\Http\Controllers\MpGestionController;

//Auth user info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // SKU / Productos
    Route::get('/productos', [SkuController::class, 'index']);
    Route::post('/productos', [SkuController::class, 'store']);
    Route::get('/productos/{sku}', [SkuController::class, 'show']);
    Route::put('/productos/{id}', [SkuController::class, 'update']);
    Route::delete('/productos/{id}', [SkuController::class, 'destroy']);
    Route::patch('/productos/{id}/deactivate', [SkuController::class, 'deactivate']);

    // Reporte de inventario 
    Route::get('/inventario', [SkuController::class, 'inventario']);
    Route::get('/inventario/reporte', [SkuController::class, 'reporteInventario']);

    // Stock (legacy / obsoleto)
    Route::get('/stock/{sku}', [StockController::class, 'consultarStock']);
    Route::post('/stock/ingreso', [StockController::class, 'agregarStock']);
});

// MOVIMIENTOS (Protegidos)
Route::middleware('auth:sanctum')->group(function () {
    // 1. PRIMERO LAS RUTAS ESPECÍFICAS (TEXTO FIJO)
    Route::get('/movimientos/ingresos', [MovimientoController::class, 'indexIngresos']);
    Route::get('/movimientos/general', [MovimientoController::class, 'historialGeneral']);
    Route::get('/movimientos/lote-items', [MovimientoController::class, 'loteItems']);

    // Rutas de escritura y gestión
    Route::post('/movimientos/ingresos', [MovimientoController::class, 'storeIngreso']);
    Route::post('/movimientos/ingresos/{id}/anular', [MovimientoController::class, 'anularIngreso']);
    Route::post('/movimientos/salida', [MovimientoController::class, 'storeSalidaCotizacion']);
    Route::post('/movimientos/importar', [MovimientoController::class, 'importarExcel']);
});

// Rutas públicas o semi-públicas (lectura)
Route::get('/movimientos/pdf', [MovimientoController::class, 'descargarPdf']);
Route::get('/movimientos/pdf/individual', [MovimientoController::class, 'descargarPdfIndividual']);
Route::get('/movimientos/{id}', [MovimientoController::class, 'show']);
Route::get('/productos/{sku}/movimientos', [MovimientoController::class, 'historialPorSku']);
// Nueva ruta para PDF por SKU
Route::get('/productos/{sku}/movimientos/pdf', [MovimientoController::class, 'descargarPdfPorSku']);

// Catálogos
Route::get('/catalogos/todos', [CatalogoController::class, 'index']);
Route::get('/catalogos/unidades', [CatalogoController::class, 'unidades']);
Route::get('/catalogos/origenes', [CatalogoController::class, 'origenes']);
Route::get('/catalogos/grupos', [CatalogoController::class, 'gruposFamilia']);
Route::get('/catalogos/familias', [CatalogoController::class, 'familias']);
Route::get('/catalogos/subfamilias', [CatalogoController::class, 'subfamilias']);
Route::get('/catalogos/tipos', [CatalogoController::class, 'tipos']);
Route::get('/catalogos/formatos', [CatalogoController::class, 'formatos']);
Route::get('/catalogos/generos', [CatalogoController::class, 'generos']);
Route::get('/catalogos/colores', [CatalogoController::class, 'colores']);
Route::get('/catalogos/tallas', [CatalogoController::class, 'tallas']);

// ==========================================
// MÓDULO COMERCIAL
// ==========================================

Route::middleware('auth:sanctum')->group(function () {
    // Oportunidades
    Route::apiResource('oportunidades', OportunidadController::class)
        ->parameters(['oportunidades' => 'oportunidad']);

    Route::patch(
        'oportunidades/{oportunidad}/estado',
        [OportunidadController::class, 'updateEstado']
    );

    Route::get('oportunidades/{oportunidad}/gestiones', [\App\Http\Controllers\Api\OportunidadGestionController::class, 'index']);
    Route::post('oportunidades/{oportunidad}/gestiones', [\App\Http\Controllers\Api\OportunidadGestionController::class, 'store']);
    Route::post('oportunidades/{oportunidad}/pasar-a-venta', [OportunidadController::class, 'pasarAVenta']);

    // Estados
    Route::get('/estados', [EstadoController::class, 'index']);

    // Ejecutivos
    Route::get('/ejecutivos', [UserController::class, 'ejecutivos']);

    // Clientes
    Route::apiResource('clientes', ClienteController::class);
    Route::post('/clientes/{cliente}/logo', [ClienteController::class, 'uploadLogo']);

    // Contactos
    Route::get('/clientes/{cliente}/contactos', [ContactoController::class, 'index']);
    Route::post('/contactos', [ContactoController::class, 'store']);
    Route::patch('/contactos/{contacto}', [ContactoController::class, 'update']);
    Route::delete('/contactos/{contacto}', [ContactoController::class, 'destroy']);

    // CALENDARIO / EVENTOS (NUEVO)
    Route::apiResource('eventos', EventoController::class);
});

// Proveedores
//Route::apiResource('proveedores', ProveedorController::class);

// Geografia
Route::get('/geografia/zonas', [App\Http\Controllers\GeografiaController::class, 'zonas']);
Route::get('/geografia/regiones', [App\Http\Controllers\GeografiaController::class, 'regiones']);
Route::get('/geografia/provincias', [App\Http\Controllers\GeografiaController::class, 'provincias']);

// AUTH - Login necesita 'web' para sesión/CSRF. Las demás usan Bearer token.
Route::middleware('web')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Estas rutas usan Bearer token (auth:sanctum) — NO necesitan sesión web
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::get('/login', function () {
    return response()->json([
        'message' => 'No autenticado. Por favor inicie sesión.'
    ], 401);
})->name('login');

//Usuarios ejecutivos (USERS con role ejecutivo/superadmin)
Route::middleware('auth:sanctum')->get('/ejecutivos', [UserController::class, 'ejecutivos']);
Route::middleware('auth:sanctum')->get('/usuarios/ejecutivos', [UserController::class, 'ejecutivos']);
Route::apiResource('users', UserController::class)->middleware('auth:sanctum');

// ==========================================
// BODEGA MP
// ==========================================
Route::prefix('bodega')->group(function () {

    // 1. LISTAR EXISTENCIAS
    Route::get('/existencias', [MpGestionController::class, 'index']);

    // 2. DETALLE DE LOTES
    Route::get('/material/{id}/lotes', [MpGestionController::class, 'getLotesByMaterial']);
    Route::get('/proveedores', [MpGestionController::class, 'indexProveedores']);
    Route::post('/crear-material', [MpGestionController::class, 'storeMaterial']);
    Route::post('/crear-proveedor', [MpGestionController::class, 'storeProveedor']);
    Route::put('/proveedor/{id}', [MpGestionController::class, 'updateProveedor']);
    Route::delete('/proveedor/{id}', [MpGestionController::class, 'destroyProveedor']);

    Route::get('/formulario-datos', [MpGestionController::class, 'getDatosFormulario']);
    Route::post('/ingreso', [MpGestionController::class, 'registrarIngreso']);
    Route::post('/sync-catalogo', [MpGestionController::class, 'syncCatalogoProveedor']);
    Route::get('/catalogo-completo', [MpGestionController::class, 'getCatalogoCompleto']);

    // CRUD / Legacy
    Route::get('/materiales', [MpGestionController::class, 'index']);
    Route::get('/materiales/{id}', [MpMaterialController::class, 'show']);
    Route::put('/material/{id}', [MpGestionController::class, 'updateMaterial']);
    Route::delete('/material/{id}', [MpGestionController::class, 'destroyMaterial']);
    Route::put('/lote/{id}', [MpGestionController::class, 'updateLote']);
    Route::delete('/lote/{id}', [MpGestionController::class, 'destroyLote']);
});

// ==========================================
// ALERTAS / REPORTES
// ==========================================
Route::prefix('alertas')->group(function () {
    // 1. Obtener datos para las tablas (PTE, MP y Config)
    Route::get('/criticos', [AlertasController::class, 'getCriticos']);

    // 2. Guardar la configuración (horarios, emails automáticos)
    Route::post('/config', [AlertasController::class, 'updateConfig']);

    // 3. Enviar reporte manual ("Enviar Ahora")
    Route::post('/enviar', [AlertasController::class, 'enviarReporte']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/order-requests', [OrderRequestController::class, 'index']);
    Route::get('/order-requests/count', [OrderRequestController::class, 'countPending']);
    Route::get('/order-requests/quotes', [OrderRequestController::class, 'quotes']);
    Route::post('/order-requests', [OrderRequestController::class, 'store']);
    Route::post('/order-requests/bulk-confirm', [OrderRequestController::class, 'bulkConfirm']);
    Route::post('/order-requests/bulk-reject', [OrderRequestController::class, 'bulkReject']);
    Route::post('/order-requests/{id}/confirm', [OrderRequestController::class, 'confirm']);
    Route::post('/order-requests/{id}/reject', [OrderRequestController::class, 'reject']);
    Route::get('/order-requests/{id}/pdf', [OrderRequestController::class, 'downloadRequestPdf']);
    Route::get('/order-requests/quote/{id}/pdf', [OrderRequestController::class, 'downloadQuoteRequestPdf']);
    Route::get('/order-requests/{id}/dispatch-pdf', [OrderRequestController::class, 'downloadDispatchPdf']);
});

// Cotizaciones options (no requiere autenticación)
Route::get('/cotizaciones/options', [\App\Http\Controllers\Api\CotizacionOptionsController::class, 'index']);

// Cotizaciones (requieren autenticación para $request->user())
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cotizaciones', [CotizacionController::class, 'index']);
    Route::post('/cotizaciones', [CotizacionController::class, 'store']);
    Route::get('/cotizaciones/{cotizacion}', [CotizacionController::class, 'show']);
    Route::patch('/cotizaciones/{cotizacion}', [CotizacionController::class, 'update']);
    Route::delete('/cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy']);
    Route::patch('/cotizaciones/{cotizacion}/estado', [CotizacionController::class, 'updateEstado']);
    Route::patch('/cotizaciones/{cotizacion}/personalizacion-completada', [CotizacionController::class, 'marcarPersonalizacionCompletada']);
    Route::post('/cotizaciones/{cotizacion}/gestion', [CotizacionController::class, 'storeGestion']);
    Route::post('/cotizaciones/{cotizacion}/archivos', [CotizacionController::class, 'uploadArchivo']);

    Route::get('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'show']);
    Route::put('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'update']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadMatrizImage']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload-names', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadNames']);
});

Route::get('/factory-logs', [App\Http\Controllers\Api\FactoryLogController::class, 'index']);
Route::middleware('auth:sanctum')->get('/cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'exportPdf']);
Route::get('/insumos', [App\Http\Controllers\Api\InsumoController::class, 'index']);

// ==========================================
// MÓDULO PERSONALIZACIÓN
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/personalizaciones/available-products', [\App\Http\Controllers\Api\PersonalizacionController::class, 'getAvailableProducts']);
    Route::post('/personalizaciones/bulk', [\App\Http\Controllers\Api\PersonalizacionController::class, 'storeBulk']);
    Route::post('/personalizaciones/assets', [\App\Http\Controllers\Api\PersonalizacionController::class, 'uploadAsset']);
    Route::apiResource('personalizaciones', \App\Http\Controllers\Api\PersonalizacionController::class);

    Route::get('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'show']);
    Route::put('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'update']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadMatrizImage']);
});

Route::get('/test-stock', function () {
    // Pon aquí el SKU que te está dando problemas
    $sku = '111440410317011';
    $dias = 30;
    $fechaInicio = \Carbon\Carbon::now()->subDays($dias);

    // Consulta cruda para ver qué hay en la BD
    $movimientos = \App\Models\PteMovimiento::where('sku', $sku)
        ->where('fecha_hora', '>=', $fechaInicio)
        ->get();

    $suma = $movimientos->where('cantidad', '<', 0)->sum('cantidad');

    return [
        'mensaje' => 'Diagnóstico de Stock',
        'sku_analizado' => $sku,
        'fecha_inicio_analisis' => $fechaInicio->toDateTimeString(),
        'movimientos_encontrados' => $movimientos->count(),
        'suma_salidas' => $suma, // Si esto es 0, la predicción fallará
        'detalle_movimientos' => $movimientos
    ];
});

// ── Proxy de archivos de storage (CORS habilitado para PDF export) ────────
// php artisan serve sirve /storage/ como estáticos SIN headers CORS.
// Esta ruta pasa por el middleware CORS de Laravel → permite fetch() desde Next.js.
Route::get('/storage-proxy/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');
