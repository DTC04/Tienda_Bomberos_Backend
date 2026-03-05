<?php

// This file serves as the document root entry point when the Laravel app
// is deployed by Hostinger's git integration to public_html/public/ (the document root).
// Since the entire Laravel app lives here, we bootstrap directly from __DIR__.

define('LARAVEL_START', microtime(true));

// Maintenance mode check
if (file_exists($maintenance = __DIR__ . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoloader
require __DIR__ . '/vendor/autoload.php';

// Bootstrap and handle the request
(require_once __DIR__ . '/bootstrap/app.php')
    ->handleRequest(Illuminate\Http\Request::capture());
