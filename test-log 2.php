<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test Log creation
try {
    $log = \App\Models\FactoryLog::create([
        'action' => 'Test Log',
        'description' => 'Testing log creation',
        'details' => json_encode(['test' => true]),
        'entity_type' => 'Test',
        'entity_id' => 1,
        'user_id' => null
    ]);
    echo "Log ID: " . $log->id . " created successfully.\n";
    
    // Check count
    $count = \App\Models\FactoryLog::count();
    echo "Total logs: " . $count . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
