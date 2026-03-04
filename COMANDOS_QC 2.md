# Comandos Útiles - Control de Calidad (QC)

## Migraciones

### Ejecutar la migración de QC
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/Tienda_Bomberos_SI/backend
php artisan migrate
```

### Verificar estado de migraciones
```bash
php artisan migrate:status
```

### Revertir última migración
```bash
php artisan migrate:rollback --step=1
```

### Revertir todas las migraciones y volver a ejecutar
```bash
php artisan migrate:refresh
```

## Seeders

### Ejecutar seeder de órdenes con QC
```bash
php artisan db:seed --class=CuttingOrderQcSeeder
```

### Ejecutar todos los seeders
```bash
php artisan db:seed
```

### Limpiar y volver a poblar la base de datos
```bash
php artisan migrate:fresh --seed
```

## Tinker - Comandos de Prueba

### Abrir consola interactiva
```bash
php artisan tinker
```

### Dentro de Tinker:

#### Ver todas las órdenes con sus contadores
```php
CuttingOrder::all()->map(fn($o) => [
    'code' => $o->code,
    'status' => $o->status,
    'total' => $o->total_unidades,
    'taller' => $o->unidades_en_taller,
    'reparacion' => $o->unidades_en_reparacion,
    'listas' => $o->unidades_listas,
    'progreso' => $o->getQcProgressPercentage() . '%'
]);
```

#### Registrar entrega parcial
```php
$order = CuttingOrder::where('code', '1002')->first();
$order->registrarEntregaParcial(15, 3); // 15 listas, 3 a reparación
$order->refresh();
echo "Progreso: " . $order->getQcProgressPercentage() . "%";
```

#### Verificar si puede cambiar de estado
```php
$order = CuttingOrder::find(1);
$order->canExitQualityControl(); // true/false
$order->canMoveToFinished(); // true/false
$order->getUnidadesFaltantes(); // número de unidades faltantes
```

#### Órdenes con QC incompleto
```php
CuttingOrder::qcIncomplete()->get();
```

#### Órdenes con QC completo
```php
CuttingOrder::qcComplete()->get();
```

#### Reiniciar contadores de una orden
```php
$order = CuttingOrder::find(1);
$order->resetQcCounters();
```

## Testing con curl

### Ver todas las órdenes
```bash
curl -X GET http://localhost:8000/api/cutting-orders \
  -H "Content-Type: application/json"
```

### Actualizar progreso parcial
```bash
curl -X PATCH http://localhost:8000/api/cutting-orders/1/partial-qc \
  -H "Content-Type: application/json" \
  -d '{
    "unidades_listas": 30,
    "unidades_en_reparacion": 5
  }'
```

### Intentar cambiar estado (debería validar)
```bash
curl -X PATCH http://localhost:8000/api/cutting-orders/1 \
  -H "Content-Type: application/json" \
  -d '{
    "status": "finished"
  }'
```

## Consultas SQL Directas

### Ver resumen de todas las órdenes
```sql
SELECT 
    code,
    status,
    total_unidades,
    unidades_en_taller,
    unidades_en_reparacion,
    unidades_listas,
    ROUND((unidades_listas * 100.0 / NULLIF(total_unidades, 0)), 1) as porcentaje_completado
FROM ordenes_corte
ORDER BY code;
```

### Órdenes en QC con progreso
```sql
SELECT 
    code,
    client,
    total_unidades,
    unidades_listas,
    CONCAT(ROUND((unidades_listas * 100.0 / total_unidades), 1), '%') as progreso
FROM ordenes_corte
WHERE status = 'quality-control'
ORDER BY (unidades_listas * 100.0 / total_unidades) DESC;
```

### Órdenes listas para finalizar
```sql
SELECT code, client, status
FROM ordenes_corte
WHERE status = 'quality-control'
AND unidades_listas >= total_unidades;
```

### Órdenes con unidades en reparación
```sql
SELECT 
    code,
    client,
    unidades_en_reparacion,
    total_unidades,
    ROUND((unidades_en_reparacion * 100.0 / total_unidades), 1) as porcentaje_reparacion
FROM ordenes_corte
WHERE unidades_en_reparacion > 0
ORDER BY porcentaje_reparacion DESC;
```

## Comandos de Desarrollo

### Limpiar caché
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Ver rutas de la API
```bash
php artisan route:list --path=api/cutting
```

### Generar documentación de modelo
```bash
php artisan model:show CuttingOrder
```

### Crear nueva migración (si necesitas modificar)
```bash
php artisan make:migration add_new_column_to_ordenes_corte_table
```

### Crear nuevo seeder
```bash
php artisan make:seeder CustomOrderSeeder
```

## Testing Automático

### Ejecutar tests
```bash
php artisan test
```

### Ejecutar test específico
```bash
php artisan test --filter=CuttingOrderTest
```

### Ejecutar tests con coverage
```bash
php artisan test --coverage
```

## Logs y Debug

### Ver logs en tiempo real
```bash
tail -f storage/logs/laravel.log
```

### Limpiar logs
```bash
> storage/logs/laravel.log
```

### Ver últimas 50 líneas de log
```bash
tail -n 50 storage/logs/laravel.log
```

## Base de Datos

### Conectar a MySQL
```bash
mysql -u root -p
```

### Seleccionar base de datos
```sql
USE tienda_bomberos;
```

### Ver estructura de tabla
```sql
DESCRIBE ordenes_corte;
```

### Backup de la base de datos
```bash
mysqldump -u root -p tienda_bomberos > backup_$(date +%Y%m%d).sql
```

### Restaurar backup
```bash
mysql -u root -p tienda_bomberos < backup_20260224.sql
```

## Verificación de Integridad

### Script de verificación de integridad de datos
```php
// En Tinker
$orders = CuttingOrder::all();
$errors = [];

foreach ($orders as $order) {
    $total = $order->unidades_en_taller + 
             $order->unidades_en_reparacion + 
             $order->unidades_listas;
    
    if ($total != $order->total_unidades) {
        $errors[] = [
            'code' => $order->code,
            'expected' => $order->total_unidades,
            'actual' => $total,
            'diff' => $order->total_unidades - $total
        ];
    }
}

if (empty($errors)) {
    echo "✅ Todos los datos son consistentes\n";
} else {
    echo "❌ Se encontraron " . count($errors) . " inconsistencias:\n";
    print_r($errors);
}
```

## Comandos de Emergencia

### Resetear contadores de todas las órdenes
```php
// En Tinker - ¡USAR CON PRECAUCIÓN!
CuttingOrder::where('status', 'quality-control')->each(function($order) {
    $order->resetQcCounters();
    echo "Reset: " . $order->code . "\n";
});
```

### Recalcular unidades_en_taller
```php
// En Tinker
CuttingOrder::all()->each(function($order) {
    $procesadas = $order->unidades_listas + $order->unidades_en_reparacion;
    $order->unidades_en_taller = max(0, $order->total_unidades - $procesadas);
    $order->save();
});
```

## Notas Importantes

1. **Siempre hacer backup** antes de ejecutar migraciones en producción
2. **Verificar integridad** después de operaciones masivas
3. **Usar transacciones** para operaciones críticas
4. **Revisar logs** después de cada cambio importante
5. **Probar en development** antes de aplicar en producción

## Scripts Útiles

### Script para generar reporte de QC
```bash
php artisan tinker --execute="
\$report = CuttingOrder::where('status', 'quality-control')
    ->get()
    ->map(fn(\$o) => [
        'Código' => \$o->code,
        'Cliente' => \$o->client,
        'Total' => \$o->total_unidades,
        'Listas' => \$o->unidades_listas,
        'Reparación' => \$o->unidades_en_reparacion,
        'En Taller' => \$o->unidades_en_taller,
        'Progreso' => \$o->getQcProgressPercentage() . '%'
    ]);
echo json_encode(\$report, JSON_PRETTY_PRINT);
"
```

## Enlaces Útiles

- Documentación del modelo: `MODELO_CONTROL_CALIDAD.md`
- Validaciones del controlador: `VALIDACION_CONTROL_CALIDAD.md`
- Laravel Documentation: https://laravel.com/docs
